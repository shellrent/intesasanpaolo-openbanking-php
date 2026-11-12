<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Exception;

use Throwable;

/**
 * Implemented by every exception thrown by the SDK: catch it to handle all SDK failures at once.
 */
interface OpenBankingException extends Throwable {}
