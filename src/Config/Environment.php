<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Config;

/**
 * Intesa Sanpaolo API environment.
 *
 * Both environments expose the same APIs; the sandbox returns fictitious data.
 */
enum Environment: string
{
    case Live = 'live';
    case Sandbox = 'sandbox';
}
