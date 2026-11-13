<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Exception;

use InvalidArgumentException;

/**
 * The SDK configuration is invalid, e.g. an empty credential or a missing certificate file.
 */
final class ConfigurationException extends InvalidArgumentException implements OpenBankingException {}
