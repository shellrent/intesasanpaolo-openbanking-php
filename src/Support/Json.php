<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Support;

use JsonException;

/**
 * Deterministic JSON encoding of request bodies (docs/decisions.md, D-38).
 *
 * json_encode() writes floats according to the "serialize_precision" ini setting: with a
 * non-default value an amount of 0.3 could be sent as 0.29999999999999999. The encoding
 * always uses the shortest exact representation (-1, the PHP default), whatever the setting.
 *
 * The flags are the same as Guzzle's "json" option used by the 2.x SDK, so the bytes sent do not change.
 *
 * @internal
 */
final class Json
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws JsonException
     */
    public static function encode(array $data): string
    {
        $previous = ini_get('serialize_precision');
        ini_set('serialize_precision', '-1');

        try {
            return json_encode($data, JSON_THROW_ON_ERROR);
        } finally {
            if ($previous !== false) {
                ini_set('serialize_precision', $previous);
            }
        }
    }
}
