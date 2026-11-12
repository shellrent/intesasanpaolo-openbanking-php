<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Support;

use DateTimeImmutable;

/**
 * Source of the current time, replaceable in tests.
 *
 * @internal
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
