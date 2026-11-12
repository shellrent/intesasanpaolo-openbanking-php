<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Support;

/**
 * Optional helper for the texts of SEPA payments (names, payment information).
 *
 * The SDK sends texts as they are, like the 2.x SDK did (docs/decisions.md, D-39): use this
 * helper when you want to stay within the characters accepted by Intesa Sanpaolo
 * (docs/api-quirks.md, Q-39): a-z A-Z 0-9 / - ? : ( ) . , ' + and space.
 *
 * Beware: changing the creditor name can change the outcome of the Verification of Payee.
 */
final class SepaText
{
    private const string ALLOWED_PATTERN = "/^[A-Za-z0-9\\/\\-?:().,'+ ]*$/";

    /**
     * Replacements for characters outside the allowed set; anything not listed is removed.
     */
    private const array TRANSLITERATIONS = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae',
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'œ' => 'oe',
        'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Œ' => 'OE',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'ç' => 'c', 'Ç' => 'C', 'ñ' => 'n', 'Ñ' => 'N', 'ß' => 'ss', 'ý' => 'y', 'ÿ' => 'y', 'Ý' => 'Y',
        '’' => "'", '‘' => "'", '`' => "'", '´' => "'",
        '–' => '-', '—' => '-', '_' => '-',
        '&' => '+',
    ];

    /**
     * Whether the text contains only the characters accepted by the bank.
     */
    public static function isValid(string $text): bool
    {
        return preg_match(self::ALLOWED_PATTERN, $text) === 1;
    }

    /**
     * Transliterates accented letters (à → a), converts common symbols ("&" → "+", "’" → "'"),
     * removes any other character outside the allowed set, collapses spaces and, if requested,
     * truncates to a maximum length.
     */
    public static function sanitize(string $text, ?int $maxLength = null): string
    {
        $text = strtr($text, self::TRANSLITERATIONS);
        $text = (string) preg_replace("/[^A-Za-z0-9\\/\\-?:().,'+ ]/", ' ', $text);
        $text = trim((string) preg_replace('/ {2,}/', ' ', $text));

        if ($maxLength !== null && strlen($text) > $maxLength) {
            $text = rtrim(substr($text, 0, max(0, $maxLength)));
        }

        return $text;
    }
}
