<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Support;

use DateInterval;
use DateTimeImmutable;
use Override;
use Shellrent\OpenBanking\Support\Clock;

final class FrozenClock implements Clock
{
    public function __construct(private DateTimeImmutable $now = new DateTimeImmutable('2026-01-15 10:00:00')) {}

    #[Override]
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->add(new DateInterval(sprintf('PT%dS', $seconds)));
    }
}
