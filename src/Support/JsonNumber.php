<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Support;

use InvalidArgumentException;

/**
 * A number written into a request body exactly as given, without passing through a float (D-65).
 *
 * Json::encode() is the only place that turns it into JSON.
 *
 * @internal
 */
final readonly class JsonNumber
{
    public string $literal;

    /**
     * @param  string  $literal  JSON number without exponent, e.g. "1250.1".
     *
     * @throws InvalidArgumentException When the literal is not a plain JSON number.
     */
    public function __construct(string $literal)
    {
        if (preg_match('/^-?(0|[1-9]\d*)(\.\d+)?$/', $literal) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a plain JSON number.', $literal));
        }

        $this->literal = $literal;
    }

    /**
     * An amount with two decimals ("1250.10"), written as the 2.x SDK wrote it through json_encode()
     * of a float: without trailing zeros ("1250.1", "0.3") and without decimals when they are zero
     * ("1250"), so the bytes sent to the bank do not change.
     */
    public static function amount(string $amount): self
    {
        [$integer, $fraction] = explode('.', $amount, 2) + [1 => ''];
        $fraction = rtrim($fraction, '0');

        return new self($fraction === '' ? $integer : $integer.'.'.$fraction);
    }
}
