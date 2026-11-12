<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Auth;

use DateTimeImmutable;
use JsonException;
use Shellrent\OpenBanking\Config\ClientConfig;
use Shellrent\OpenBanking\Exception\ApiError;
use Shellrent\OpenBanking\Exception\ApiException;
use Shellrent\OpenBanking\Exception\AuthenticationException;
use Shellrent\OpenBanking\Exception\UnexpectedResponseException;
use Shellrent\OpenBanking\Http\Sender;
use Shellrent\OpenBanking\Support\Clock;
use Shellrent\OpenBanking\Support\SystemClock;
use stdClass;

/**
 * Obtains and caches the OAuth2 token (client credentials flow, docs/api-quirks.md, Q-02).
 *
 * @internal
 */
final class TokenProvider
{
    /**
     * A token is renewed this many seconds before its expiry, so it never expires during a request.
     */
    public const int EXPIRY_MARGIN_SECONDS = 60;

    public function __construct(
        private readonly ClientConfig $config,
        private readonly Sender $sender,
        private readonly TokenStore $store = new InMemoryTokenStore,
        private readonly Clock $clock = new SystemClock,
    ) {}

    /**
     * A valid token, from the store or from a new token request.
     *
     * @phpstan-impure
     *
     * @throws AuthenticationException When the token request is rejected.
     */
    public function token(): AccessToken
    {
        $now = $this->clock->now();
        $cached = $this->store->get($this->key());

        if ($cached !== null && $cached->isValidAt($now, self::EXPIRY_MARGIN_SECONDS)) {
            return $cached;
        }

        $token = $this->request($now);
        $this->store->put($this->key(), $token);

        return $token;
    }

    /**
     * Discards the stored token, e.g. after an HTTP 401, so that the next call requests a new one.
     */
    public function invalidate(): void
    {
        $this->store->forget($this->key());
    }

    private function key(): string
    {
        return 'shellrent.openbanking.token.'.hash('sha256', $this->config->environment->value.'|'.$this->config->clientId);
    }

    private function request(DateTimeImmutable $now): AccessToken
    {
        $uri = $this->config->tokenUri();

        $response = $this->sender->send(
            'POST',
            $uri,
            headers: ['Authorization' => $this->config->basicAuthorization()],
            formParams: ['grant_type' => 'client_credentials', 'scope' => 'oob'],
        );

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $decoded = self::decode($body);

        if ($status >= 400) {
            $errors = $decoded instanceof stdClass ? self::errorsOf($decoded) : [];
            $exception = $status < 500 ? AuthenticationException::class : ApiException::class;

            throw $exception::fromErrors($status, $errors, null, $body);
        }

        if (! $decoded instanceof stdClass) {
            throw new UnexpectedResponseException('The OAuth2 token response is not a JSON object.');
        }

        $value = $decoded->access_token ?? null;
        $type = $decoded->token_type ?? null;

        if (! is_string($value) || $value === '' || ! is_string($type) || strcasecmp($type, 'Bearer') !== 0) {
            throw new UnexpectedResponseException('The OAuth2 token response does not contain a bearer token.');
        }

        return new AccessToken($value, $now->modify(sprintf('+%d seconds', self::lifetime($decoded->expires_in ?? null))));
    }

    /**
     * Lifetime in seconds; without a valid "expires_in" the token is used for the current request only.
     */
    private static function lifetime(mixed $expiresIn): int
    {
        if (is_string($expiresIn) && ctype_digit($expiresIn)) {
            $expiresIn = (int) $expiresIn;
        }

        return is_int($expiresIn) && $expiresIn > 0 ? $expiresIn : 0;
    }

    /**
     * Errors of a failed token request: OAuth2 format ({"error", "error_description"}) or API format ("errors").
     *
     * @return list<ApiError>
     */
    private static function errorsOf(stdClass $decoded): array
    {
        $error = $decoded->error ?? null;

        if (is_string($error) && $error !== '') {
            $description = $decoded->error_description ?? null;

            return [new ApiError($error, null, is_string($description) && $description !== '' ? $description : null)];
        }

        return ApiError::listFrom($decoded->errors ?? null);
    }

    private static function decode(string $body): mixed
    {
        if ($body === '') {
            return null;
        }

        try {
            return json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }
}
