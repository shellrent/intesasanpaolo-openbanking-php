<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Http;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Shellrent\OpenBanking\Config\ClientConfig;
use Shellrent\OpenBanking\Exception\TransportException;
use Throwable;

/**
 * Sends HTTP requests through Guzzle with the options shared by every call.
 *
 * - The mutual TLS certificate and the timeouts are applied per request, so they also apply
 *   to a Guzzle client provided by the application (e.g. with logging middleware).
 * - HTTP error statuses do not raise Guzzle exceptions ("http_errors" is off): callers inspect the
 *   status themselves, which behaves the same on Guzzle 7 and 8.
 * - Transfer failures become TransportException. Nothing is retried automatically: retrying a
 *   payment could execute it twice.
 *
 * @internal
 */
final class Sender
{
    public function __construct(
        private readonly ClientConfig $config,
        private readonly ClientInterface $http,
    ) {}

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, scalar>  $query  Replaces the query string of the URI when not empty.
     * @param  mixed  $json  Body sent as JSON by Guzzle, when not null.
     * @param  string|null  $body  Raw body, when not null (the caller sets the Content-Type).
     * @param  array<string, string>|null  $formParams  Body sent as "application/x-www-form-urlencoded", when not null.
     *
     * @throws TransportException When the request does not complete.
     *
     * @phpstan-impure
     */
    public function send(
        string $method,
        string $uri,
        array $headers = [],
        array $query = [],
        mixed $json = null,
        ?array $formParams = null,
        ?string $body = null,
    ): ResponseInterface {
        $options = [
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::CONNECT_TIMEOUT => $this->config->connectTimeout,
            RequestOptions::TIMEOUT => $this->config->timeout,
            RequestOptions::HEADERS => $headers,
            ...$this->config->certificate->toGuzzleOptions(),
        ];

        if ($query !== []) {
            $options[RequestOptions::QUERY] = $query;
        }

        if ($json !== null) {
            $options[RequestOptions::JSON] = $json;
        }

        if ($formParams !== null) {
            $options[RequestOptions::FORM_PARAMS] = $formParams;
        }

        if ($body !== null) {
            $options[RequestOptions::BODY] = $body;
        }

        try {
            return $this->http->request($method, $uri, $options);
        } catch (NetworkExceptionInterface $exception) {
            throw new TransportException(
                sprintf('Network error on %s %s: %s%s', $method, $uri, $exception->getMessage(), self::hint($exception)),
                $method,
                $uri,
                $exception,
            );
        } catch (GuzzleException $exception) {
            throw new TransportException(
                sprintf('HTTP transfer error on %s %s: %s%s', $method, $uri, $exception->getMessage(), self::hint($exception)),
                $method,
                $uri,
                $exception,
            );
        }
    }

    /**
     * Explains the most common certificate failure (docs/api-quirks.md, Q-48).
     */
    private static function hint(Throwable $exception): string
    {
        if (stripos($exception->getMessage(), 'PKCS12') === false) {
            return '';
        }

        return ' (the PKCS#12 file may use a legacy encryption that OpenSSL 3 cannot read:'
            .' convert it to PEM, see the README section on the certificate)';
    }
}
