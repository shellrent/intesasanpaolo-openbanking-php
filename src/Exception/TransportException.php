<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Exception;

use RuntimeException;
use Throwable;

/**
 * The request did not complete: connection failure, timeout, TLS error or interrupted response.
 *
 * For requests that move money (payment executions) the outcome is **unknown**: the bank may have
 * received and processed the request. Check the payment status before retrying.
 */
final class TransportException extends RuntimeException implements OpenBankingException
{
    public function __construct(
        string $message,
        public readonly string $method,
        public readonly string $uri,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
