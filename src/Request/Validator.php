<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Request;

use Shellrent\OpenBanking\Exception\InvalidRequestException;
use Shellrent\OpenBanking\Support\EndToEndId;

/**
 * Checks and normalizes the fields of the payment requests (docs/v3/DECISIONS.md, D-38 and D-39).
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

    public const int MAX_END_TO_END_ID_LENGTH = 35;

    /**
     * Amounts are compared in cents with this tolerance, to absorb float errors such as 0.1 + 0.2.
     */
    private const float CENT_TOLERANCE = 0.000001;

    public static function text(string $value, string $field, int $maxLength): string
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidRequestException(sprintf('The %s must not be empty.', $field));
        }

        if (self::length($value) > $maxLength) {
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
     * Validates a positive amount with at most two decimals and rounds away float noise
     * (0.30000000000000004 becomes 0.3, 10.567 is rejected).
     */
    public static function amount(float $amount): float
    {
        if (! is_finite($amount) || $amount <= 0) {
            throw new InvalidRequestException('The amount must be a positive number.');
        }

        $cents = $amount * 100;

        if (abs($cents - round($cents)) > self::CENT_TOLERANCE) {
            throw new InvalidRequestException(sprintf('The amount %s has more than two decimals.', (string) $amount));
        }

        return round($amount, 2);
    }

    /**
     * Empty values and the "NOTPROVIDED" placeholder become null (docs/api-quirks.md, Q-35).
     */
    public static function endToEndId(?string $value): ?string
    {
        $value = EndToEndId::normalize($value);

        if ($value !== null && self::length($value) > self::MAX_END_TO_END_ID_LENGTH) {
            throw new InvalidRequestException(sprintf('The end-to-end ID must be at most %d characters long.', self::MAX_END_TO_END_ID_LENGTH));
        }

        return $value;
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

    public static function optional(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Number of characters of a UTF-8 string, without requiring the mbstring extension.
     */
    private static function length(string $value): int
    {
        return (int) preg_match_all('/./su', $value);
    }
}
