<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Enum;

/**
 * Tolerant conversion of an API value into an enum case (D-11).
 *
 * The match ignores case and surrounding spaces, and uses only the first word, because the APIs
 * sometimes append a description to the code (e.g. "ACSC acquired", docs/api-quirks.md, Q-25).
 * Unknown values give null instead of an error: DTOs always keep the original value as well.
 *
 * @internal
 */
trait FromApiValue
{
    public static function fromApi(?string $value): ?self
    {
        $code = preg_split('/\s+/', trim((string) $value))[0] ?? '';

        if ($code === '') {
            return null;
        }

        foreach (self::cases() as $case) {
            if (strcasecmp((string) $case->value, $code) === 0) {
                return $case;
            }
        }

        return self::aliases()[strtolower($code)] ?? null;
    }

    /**
     * Alternative spellings seen in the APIs, keyed by lowercase value.
     *
     * @return array<string, self>
     */
    private static function aliases(): array
    {
        return [];
    }
}
