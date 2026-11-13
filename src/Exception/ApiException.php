<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Exception;

use RuntimeException;
use Throwable;

/**
 * The API rejected the request: HTTP error status, or a response with "success": false.
 */
class ApiException extends RuntimeException implements OpenBankingException
{
    /**
     * Maximum length of the response body kept for debugging.
     */
    public const int MAX_BODY_LENGTH = 1000;

    /**
     * @param  int  $statusCode  HTTP status code of the response.
     * @param  list<ApiError>  $errors  Errors reported by the API.
     * @param  string|null  $transactionId  Value of the "X-ISP-Transaction-ID" response header, to quote to the ISP support.
     * @param  string|null  $responseBody  Beginning of the response body, for debugging.
     */
    final public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly array $errors = [],
        public readonly ?string $transactionId = null,
        public readonly ?string $responseBody = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    /**
     * @param  mixed  $errors  The "errors" value of the decoded response, in any of the known formats.
     */
    public static function fromResponse(int $statusCode, mixed $errors, ?string $transactionId, string $body): static
    {
        return static::fromErrors($statusCode, ApiError::listFrom($errors), $transactionId, $body);
    }

    /**
     * @param  list<ApiError>  $errors
     */
    public static function fromErrors(int $statusCode, array $errors, ?string $transactionId, string $body): static
    {
        $summary = $errors === []
            ? 'no error details in the response'
            : implode('; ', array_map(static fn (ApiError $error): string => (string) $error, $errors));

        return new static(
            sprintf('Intesa Sanpaolo API error (HTTP %d): %s', $statusCode, $summary),
            $statusCode,
            $errors,
            $transactionId,
            $body === '' ? null : substr($body, 0, self::MAX_BODY_LENGTH),
        );
    }
}
