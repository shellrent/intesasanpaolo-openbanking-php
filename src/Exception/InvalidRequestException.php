<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Exception;

use InvalidArgumentException;

/**
 * The request data is invalid and was not sent: missing or too long field, invalid IBAN or amount,
 * or a simulation that does not belong to the payment being executed.
 */
final class InvalidRequestException extends InvalidArgumentException implements OpenBankingException {}
