<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Http;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Shellrent\OpenBanking\Auth\TokenProvider;
use Shellrent\OpenBanking\Config\ClientConfig;
use Shellrent\OpenBanking\Exception\ApiException;
use Shellrent\OpenBanking\Exception\AuthenticationException;
use Shellrent\OpenBanking\Exception\TransportException;
use Shellrent\OpenBanking\Exception\UnexpectedResponseException;
use Shellrent\OpenBanking\Support\Json;
use Shellrent\OpenBanking\Support\Payload;
use stdClass;

/**
 * Authenticated calls to the APIs and reading of the response envelope
 * `{ "success": bool, "errors": ..., "payload": ... }` (docs/api-quirks.md, Q-05).
 *
 * Every call carries the same headers as the 2.x SDK, which are known to work in production:
 * "Authorization: Bearer <token>" and "apikey: <client ID>" (Q-03), plus "Content-Type"
 * for JSON bodies or when the caller asks for it (Q-04).
 *
 * @internal
 */
final class Transport
{
    public const string TRANSACTION_ID_HEADER = 'X-ISP-Transaction-ID';

    public function __construct(
        private readonly ClientConfig $config,
        private readonly Sender $sender,
        private readonly TokenProvider $tokens,
    ) {}

    /**
     * @param  string  $pathOrUrl  Path relative to the API base URI (e.g. "accounts/IT.../balance") or absolute URL (pagination links).
     * @param  array<string, scalar>  $query
     * @param  array<string, string>  $headers  Additional request headers.
     * @param  bool  $acceptNegativeOutcome  Return the payload of a "success: false" response without errors instead
     *                                       of throwing: some endpoints answer a negative outcome that way (Q-20, Q-31).
     *
     * @throws ApiException When the API rejects the request.
     * @throws AuthenticationException When the API answers HTTP 401 or the token request is rejected.
     * @throws TransportException When the request does not complete.
     * @throws UnexpectedResponseException When the response cannot be interpreted.
     *
     * @phpstan-impure
     */
    public function get(string $pathOrUrl, array $query = [], array $headers = [], bool $acceptNegativeOutcome = false): ApiResponse
    {
        return $this->request('GET', $pathOrUrl, $query, null, $headers, $acceptNegativeOutcome);
    }

    /**
     * @param  array<string, mixed>  $json  Request body, sent as JSON.
     * @param  array<string, string>  $headers  Additional request headers.
     *
     * @throws ApiException When the API rejects the request.
     * @throws AuthenticationException When the API answers HTTP 401 or the token request is rejected.
     * @throws TransportException When the request does not complete: for payments the outcome is unknown.
     * @throws UnexpectedResponseException When the response cannot be interpreted.
     *
     * @phpstan-impure
     */
    public function post(string $path, array $json, array $headers = []): ApiResponse
    {
        return $this->request('POST', $path, [], $json, $headers, false);
    }

    /**
     * @param  array<string, scalar>  $query
     * @param  array<string, mixed>|null  $json
     * @param  array<string, string>  $headers
     */
    private function request(
        string $method,
        string $pathOrUrl,
        array $query,
        ?array $json,
        array $headers,
        bool $acceptNegativeOutcome,
    ): ApiResponse {
        if ($json !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        $response = $this->sender->send(
            $method,
            $this->resolve($pathOrUrl),
            headers: [
                ...$headers,
                'Authorization' => 'Bearer '.$this->tokens->token()->value,
                'apikey' => $this->config->clientId,
            ],
            query: $query,
            body: $json === null ? null : Json::encode($json),
        );

        return $this->read($response, $acceptNegativeOutcome);
    }

    private function resolve(string $pathOrUrl): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $pathOrUrl) === 1) {
            // Absolute URLs come from pagination links: the credentials and the certificate go only to the ISP API.
            if (! str_starts_with(strtolower($pathOrUrl), strtolower(ClientConfig::HOST).'/')) {
                throw new UnexpectedResponseException(sprintf('The link "%s" points outside the Intesa Sanpaolo API.', $pathOrUrl));
            }

            return $pathOrUrl;
        }

        return $this->config->apiBaseUri().'/'.ltrim($pathOrUrl, '/');
    }

    private function read(ResponseInterface $response, bool $acceptNegativeOutcome): ApiResponse
    {
        $status = $response->getStatusCode();
        $headers = $response->getHeaders();
        $transactionId = $response->getHeaderLine(self::TRANSACTION_ID_HEADER);
        $transactionId = $transactionId === '' ? null : $transactionId;
        $body = (string) $response->getBody();
        $decoded = self::decode($body);

        if ($status >= 400) {
            $errors = $decoded instanceof stdClass ? ($decoded->errors ?? null) : null;

            if ($status === 401) {
                $this->tokens->invalidate();

                throw AuthenticationException::fromResponse($status, $errors, $transactionId, $body);
            }

            throw ApiException::fromResponse($status, $errors, $transactionId, $body);
        }

        if (! $decoded instanceof stdClass) {
            throw new UnexpectedResponseException(
                sprintf('The response (HTTP %d) is not a JSON object.', $status),
                $transactionId,
                responseBody: self::excerpt($body),
            );
        }

        $unsuccessful = self::isUnsuccessful($decoded);

        if ($unsuccessful && ! ($acceptNegativeOutcome && self::isNegativeOutcome($decoded))) {
            throw ApiException::fromResponse($status, $decoded->errors ?? null, $transactionId, $body);
        }

        $payload = $decoded->payload ?? null;

        // An empty JSON array is how some backends serialize an empty payload.
        if ($payload === []) {
            $payload = null;
        }

        if ($payload !== null && ! $payload instanceof stdClass) {
            throw new UnexpectedResponseException(
                'The response payload is not a JSON object.',
                $transactionId,
                responseBody: self::excerpt($body),
            );
        }

        return new ApiResponse(
            $status,
            $payload === null ? null : new Payload($payload, 'payload', $transactionId),
            $headers,
            $transactionId,
            $unsuccessful,
        );
    }

    /**
     * "success" is false, also as the string "false" or the number 0; a missing "success" is not a failure.
     */
    private static function isUnsuccessful(stdClass $decoded): bool
    {
        $success = $decoded->success ?? null;

        return $success === false
            || $success === 0
            || (is_string($success) && in_array(strtolower(trim($success)), ['false', '0'], true));
    }

    private static function excerpt(string $body): ?string
    {
        return $body === '' ? null : substr($body, 0, ApiException::MAX_BODY_LENGTH);
    }

    /**
     * A "success: false" response that carries an outcome in the payload and no errors.
     */
    private static function isNegativeOutcome(stdClass $decoded): bool
    {
        $errors = $decoded->errors ?? null;
        $payload = $decoded->payload ?? null;

        return ($errors === null || $errors === []) && $payload instanceof stdClass && get_object_vars($payload) !== [];
    }

    private static function decode(string $body): mixed
    {
        if ($body === '') {
            return null;
        }

        try {
            // Integers beyond PHP_INT_MAX stay exact as strings (e.g. numeric identifiers).
            return json_decode($body, false, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            return null;
        }
    }
}
