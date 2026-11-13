<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Support;

use InvalidArgumentException;

/**
 * Conversion between integer cents and the decimal strings used for amounts (docs/decisions.md, D-65).
 *
 * The SDK receives and returns amounts as decimal strings with two decimals ("1250.10"), never as floats.
 * These helpers are for applications that keep amounts in cents; exact, without float arithmetic.
 */
final class Amount
{
    /**
     * 125010 → "1250.10", -525 → "-5.25".
     */
    public static function fromCents(int $cents): string
    {
        $digits = str_pad((string) abs($cents), 3, '0', STR_PAD_LEFT);

        return ($cents < 0 ? '-' : '').substr($digits, 0, -2).'.'.substr($digits, -2);
    }

    /**
     * "1250.10" → 125010, "-5.25" → -525; also "1250" and "1250.1".
     *
     * @throws InvalidArgumentException When the value is not a decimal with at most two decimals.
     */
    public static function toCents(string $amount): int
    {
        if (preg_match('/^(-?)(\d{1,15})(?:\.(\d{1,2}))?$/', $amount, $parts) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a decimal amount with at most two decimals.', $amount));
        }

        $cents = (int) ($parts[2].str_pad($parts[3] ?? '', 2, '0'));

        return $parts[1] === '-' ? -$cents : $cents;
    }
}
