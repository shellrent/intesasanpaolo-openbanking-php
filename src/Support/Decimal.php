<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Support;

use InvalidArgumentException;

/**
 * Exact decimal amounts as strings, without float arithmetic (docs/decisions.md, D-65).
 *
 * @internal
 */
final class Decimal
{
    /**
     * Normalizes a decimal read from an API response: an integer, a float as decoded by json_decode(),
     * or a numeric string such as "250000.00".
     *
     * A float is converted through its shortest exact representation, the one json_encode() writes:
     * 14980.1 becomes "14980.10", while floating-point noise such as 0.30000000000000004 is refused.
     * Trailing zeros beyond the precision are accepted ("10.500" is "10.50").
     *
     * @param  int  $maxDecimals  Decimals allowed after removing the trailing zeros.
     * @return string|null The value with at least two decimals (e.g. "-525.00"), or null when it is not an exact decimal.
     */
    public static function fromApi(int|float|string $value, int $maxDecimals = 2): ?string
    {
        $text = match (true) {
            is_int($value) => (string) $value,
            is_float($value) => self::shortest($value),
            default => trim($value),
        };

        if (preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $text, $parts) !== 1) {
            return null;
        }

        $integer = ltrim($parts[2], '0');
        $fraction = rtrim($parts[3] ?? '', '0');

        if (strlen($fraction) > $maxDecimals) {
            return null;
        }

        $integer = $integer === '' ? '0' : $integer;
        $fraction = str_pad($fraction, 2, '0');
        $negative = $parts[1] === '-' && ($integer !== '0' || trim($fraction, '0') !== '');

        return ($negative ? '-' : '').$integer.'.'.$fraction;
    }

    /**
     * Reads an amount from data saved with toArray(): a decimal string, or a number from older data.
     *
     * @param  array<mixed>  $data
     *
     * @throws InvalidArgumentException When the value is present but is not an exact decimal amount.
     */
    public static function fromSaved(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        $decimal = is_int($value) || is_float($value) || is_string($value) ? self::fromApi($value) : null;

        return $decimal ?? throw new InvalidArgumentException(sprintf('The saved "%s" is not a decimal amount.', $key));
    }

    /**
     * Shortest representation of a float that reads back as the same float, as json_encode() writes it
     * with "serialize_precision" set to -1 (e.g. "14980.1", "1250.0", "0.30000000000000004", "1.0e+25").
     */
    public static function shortest(float $value): string
    {
        $previous = ini_get('serialize_precision');
        ini_set('serialize_precision', '-1');

        try {
            return var_export($value, true);
        } finally {
            if ($previous !== false) {
                ini_set('serialize_precision', $previous);
            }
        }
    }
}
