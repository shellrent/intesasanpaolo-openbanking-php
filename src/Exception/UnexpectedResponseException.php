<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Exception;

use RuntimeException;
use Throwable;

/**
 * The API answered, but the response cannot be interpreted: invalid JSON, missing required data,
 * values of an unexpected type, or broken pagination.
 */
final class UnexpectedResponseException extends RuntimeException implements OpenBankingException
{
    /**
     * @param  string|null  $transactionId  Value of the "X-ISP-Transaction-ID" response header, to quote to the ISP support.
     */
    public function __construct(
        string $message,
        public readonly ?string $transactionId = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
