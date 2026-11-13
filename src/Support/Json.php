<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Support;

use InvalidArgumentException;
use JsonException;

/**
 * Exact JSON encoding of request bodies (docs/decisions.md, D-38 and D-65).
 *
 * Amounts never pass through a float: they are JsonNumber values, written into the JSON exactly
 * as given. A float anywhere in the body is a programming error and is refused.
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
     * @throws InvalidArgumentException When the data contains a float.
     * @throws JsonException
     */
    public static function encode(array $data): string
    {
        // Each number becomes a placeholder string that cannot occur in the data, replaced after encoding.
        $numbers = [];
        $prepared = self::prepare($data, $numbers, bin2hex(random_bytes(8)));

        return strtr(json_encode($prepared, JSON_THROW_ON_ERROR), $numbers);
    }

    /**
     * @param  array<string, string>  $numbers  Quoted placeholder => JSON number literal.
     */
    private static function prepare(mixed $value, array &$numbers, string $nonce): mixed
    {
        if ($value instanceof JsonNumber) {
            $placeholder = sprintf('@number-%s-%d@', $nonce, count($numbers));
            $numbers['"'.$placeholder.'"'] = $value->literal;

            return $placeholder;
        }

        if (is_float($value)) {
            throw new InvalidArgumentException('Request bodies must not contain floats: amounts are sent as JsonNumber values.');
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::prepare($item, $numbers, $nonce);
            }
        }

        return $value;
    }
}
