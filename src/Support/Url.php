<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Support;

/**
 * @internal
 */
final class Url
{
    /**
     * Adds a query parameter to a link when the link does not carry it already.
     *
     * Used on pagination links that omit a required parameter (docs/api-quirks.md, Q-41 and Q-42).
     */
    public static function withQueryValue(?string $link, string $name, string $value): ?string
    {
        if ($link === null) {
            return null;
        }

        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

        if (array_key_exists($name, $query)) {
            return $link;
        }

        return $link.(str_contains($link, '?') ? '&' : '?').$name.'='.rawurlencode($value);
    }
}
