<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Support;

/**
 * @internal
 */
final class EndToEndId
{
    /**
     * Normalizes an end-to-end ID: empty values and the "NOTPROVIDED" placeholder that the bank
     * returns when no ID was sent (docs/api-quirks.md, Q-35) become null.
     */
    public static function normalize(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);

        if ($value === '' || strtoupper((string) preg_replace('/\s+/', '', $value)) === 'NOTPROVIDED') {
            return null;
        }

        return $value;
    }
}
