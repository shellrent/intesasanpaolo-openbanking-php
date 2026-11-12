<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Http;

use Shellrent\OpenBanking\Exception\UnexpectedResponseException;
use Shellrent\OpenBanking\Support\Payload;

/**
 * Successful API response: the "payload" of the envelope and the response headers.
 *
 * @internal
 */
final readonly class ApiResponse
{
    /**
     * @param  array<array<string>>  $headers  Response headers, as returned by PSR-7 getHeaders().
     */
    public function __construct(
        public int $statusCode,
        public ?Payload $payload,
        public array $headers,
        public ?string $transactionId,
    ) {}

    /**
     * First value of a response header (case-insensitive name), or null when it is missing or empty.
     */
    public function header(string $name): ?string
    {
        foreach ($this->headers as $headerName => $values) {
            if (strcasecmp((string) $headerName, $name) === 0) {
                $value = $values[0] ?? '';

                return $value === '' ? null : $value;
            }
        }

        return null;
    }

    /**
     * @throws UnexpectedResponseException When the response has no payload.
     */
    public function requirePayload(): Payload
    {
        return $this->payload ?? throw new UnexpectedResponseException('The response has no payload.', $this->transactionId);
    }
}
