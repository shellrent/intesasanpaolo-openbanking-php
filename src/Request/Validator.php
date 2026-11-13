<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Request;

use Shellrent\OpenBanking\Exception\InvalidRequestException;
use Shellrent\OpenBanking\Support\EndToEndId;

/**
 * Checks and normalizes the fields of the payment requests (docs/decisions.md, D-38 and D-39).
 *
 * Only required fields and SEPA maximum lengths are checked: characters are sent as they are,
 * like the 2.x SDK did (see SepaText for an optional clean-up).
 *
 * @internal
 */
final class Validator
{
    /**
     * Maximum lengths of the SEPA credit transfer fields (ISO 20022 pain.001).
     */
    public const int MAX_NAME_LENGTH = 70;

    public const int MAX_PAYMENT_INFORMATION_LENGTH = 140;

    /**
     * The sandbox accepts the 36-character UUIDs of its own test cases; Live accepts at most 35, the SEPA
     * maximum, and answers HTTP 500 to a longer one (docs/api-quirks.md, Q-34; D-56).
     */
    public const int MAX_END_TO_END_ID_LENGTH = 36;

    public const int MAX_LIVE_END_TO_END_ID_LENGTH = 35;

    /**
     * Integer digits of an amount: up to 999999999999.99, the range of a numeric(14,2) column.
     * The SEPA limits are lower and are enforced by the bank.
     */
    public const int MAX_AMOUNT_INTEGER_DIGITS = 12;

    public static function text(string $value, string $field, int $maxLength): string
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidRequestException(sprintf('The %s must not be empty.', $field));
        }

        if (self::length($value, $field) > $maxLength) {
            throw new InvalidRequestException(sprintf('The %s must be at most %d characters long.', $field, $maxLength));
        }

        return $value;
    }

    public static function iban(string $value, string $field): string
    {
        $normalized = strtoupper((string) preg_replace('/\s+/', '', $value));

        if (preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $normalized) !== 1) {
            throw new InvalidRequestException(sprintf('The %s is not a valid IBAN.', $field));
        }

        return $normalized;
    }

    /**
     * Validates an amount given as a decimal string and normalizes it to two decimals ("1250.1" is "1250.10").
     *
     * The format is strict (docs/decisions.md, D-65): digits, optionally a dot and one or two decimals;
     * no sign, exponent, thousands separator, comma, spaces or leading zeros. Nothing is rounded.
     */
    public static function amount(string $amount): string
    {
        $pattern = sprintf('/^(0|[1-9]\d{0,%d})(?:\.(\d{1,2}))?$/', self::MAX_AMOUNT_INTEGER_DIGITS - 1);

        if (preg_match($pattern, $amount, $parts) !== 1) {
            throw new InvalidRequestException(sprintf(
                'The amount "%s" is not valid: use a positive decimal string with a dot and at most two decimals, e.g. "1250.10".',
                $amount,
            ));
        }

        $normalized = $parts[1].'.'.str_pad($parts[2] ?? '', 2, '0');

        if ($normalized === '0.00') {
            throw new InvalidRequestException('The amount must be greater than zero.');
        }

        return $normalized;
    }

    /**
     * Empty values and the "NOTPROVIDED" placeholder become null (docs/api-quirks.md, Q-35).
     */
    public static function endToEndId(?string $value): ?string
    {
        $value = EndToEndId::normalize($value);

        if ($value !== null && self::length($value, 'end-to-end ID') > self::MAX_END_TO_END_ID_LENGTH) {
            throw new InvalidRequestException(sprintf('The end-to-end ID must be at most %d characters long.', self::MAX_END_TO_END_ID_LENGTH));
        }

        return $value;
    }

    /**
     * Applies the Live limit of the end-to-end ID, before the request is sent.
     */
    public static function liveEndToEndId(?string $value): void
    {
        if ($value !== null && self::length($value, 'end-to-end ID') > self::MAX_LIVE_END_TO_END_ID_LENGTH) {
            throw new InvalidRequestException(sprintf(
                'The end-to-end ID must be at most %d characters long in Live (36 are accepted only by the sandbox).',
                self::MAX_LIVE_END_TO_END_ID_LENGTH,
            ));
        }
    }

    /**
     * Validates an uppercase alphabetic code of fixed length (ISO 4217 currency, ISO 20022 category purpose).
     */
    public static function code(string $value, string $field, int $length): string
    {
        $value = strtoupper(trim($value));

        if (preg_match(sprintf('/^[A-Z]{%d}$/', $length), $value) !== 1) {
            throw new InvalidRequestException(sprintf('The %s must be a code of %d letters.', $field, $length));
        }

        return $value;
    }

    /**
     * Normalizes an optional fiscal code or VAT number: spaces removed, letters uppercased; it must be
     * alphanumeric, 8 to 16 characters long (e.g. 16-character codice fiscale, 11-digit partita IVA).
     */
    public static function taxCode(?string $value, string $field): ?string
    {
        $value = strtoupper((string) preg_replace('/\s+/', '', (string) $value));

        if ($value === '') {
            return null;
        }

        if (preg_match('/^[A-Z0-9]{8,16}$/', $value) !== 1) {
            throw new InvalidRequestException(sprintf('The %s must be 8 to 16 letters or digits.', $field));
        }

        return $value;
    }

    public static function optional(?string $value, string $field = 'value'): ?string
    {
        $value = $value === null ? '' : trim($value);

        if ($value === '') {
            return null;
        }

        self::length($value, $field);

        return $value;
    }

    /**
     * Number of characters of a UTF-8 string, without requiring the mbstring extension.
     *
     * @throws InvalidRequestException When the text is not valid UTF-8, which the JSON body cannot carry.
     */
    private static function length(string $value, string $field): int
    {
        $length = preg_match_all('/./su', $value);

        if ($length === false) {
            throw new InvalidRequestException(sprintf('The %s is not valid UTF-8 text.', $field));
        }

        return $length;
    }
}
