<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Support;

use DateTimeImmutable;
use Override;

/**
 * @internal
 */
final class SystemClock implements Clock
{
    #[Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable;
    }
}
