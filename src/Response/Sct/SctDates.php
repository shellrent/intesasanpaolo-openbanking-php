<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Response\Sct;

use DateTimeImmutable;
use Shellrent\OpenBanking\Support\DateFormat;
use Shellrent\OpenBanking\Support\Payload;

/**
 * Dates of the SCT responses: "d/m/Y" per the spec (docs/api-quirks.md, Q-10), "Ymd" tolerated.
 *
 * @internal
 */
final class SctDates
{
    public static function read(Payload $payload, string $key): ?DateTimeImmutable
    {
        return $payload->date([DateFormat::Italian, DateFormat::Compact], $key);
    }

    /**
     * Revoke deadline: "revokeDate" (d/m/Y) combined with "revokeTime" (H:i:s) when present.
     */
    public static function revokeDate(Payload $payload): ?DateTimeImmutable
    {
        $date = self::read($payload, 'revokeDate');
        $time = $payload->string('revokeTime');

        if ($date === null || $time === null || preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $time, $parts) !== 1) {
            return $date;
        }

        return $date->setTime((int) $parts[1], (int) $parts[2], (int) ($parts[3] ?? 0));
    }

    public static function format(?DateTimeImmutable $date): ?string
    {
        return $date?->format('Y-m-d');
    }
}
