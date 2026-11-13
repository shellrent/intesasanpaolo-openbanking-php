<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Support;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Support\Json;
use Shellrent\OpenBanking\Support\JsonNumber;
use Shellrent\OpenBanking\Support\Url;
use Shellrent\OpenBanking\Tests\Unit\TestCase;

#[CoversClass(Json::class)]
#[CoversClass(JsonNumber::class)]
#[CoversClass(Url::class)]
final class JsonTest extends TestCase
{
    /**
     * D-65: amounts are written exactly from their decimal string, whatever the "serialize_precision" setting.
     */
    #[Test]
    public function it_writes_numbers_exactly_without_floats(): void
    {
        $original = ini_get('serialize_precision');
        self::assertIsString($original);

        try {
            ini_set('serialize_precision', '17');

            self::assertSame(
                '{"amount":0.3,"fees":1250,"max":999999999999.99,"nested":{"cent":0.01},"count":2}',
                Json::encode([
                    'amount' => JsonNumber::amount('0.30'),
                    'fees' => JsonNumber::amount('1250.00'),
                    'max' => JsonNumber::amount('999999999999.99'),
                    'nested' => ['cent' => JsonNumber::amount('0.01')],
                    'count' => 2,
                ]),
            );
        } finally {
            ini_set('serialize_precision', $original);
        }
    }

    /**
     * D-65: the bytes are the same the 2.x SDK sent through a float with the default precision.
     */
    #[Test]
    public function it_writes_amounts_as_the_2x_sdk_did(): void
    {
        foreach (['0.01', '0.30', '1.05', '150.25', '1250.10', '1250.00', '999999999999.99'] as $amount) {
            self::assertSame(
                json_encode(['amount' => (float) $amount], JSON_THROW_ON_ERROR),
                Json::encode(['amount' => JsonNumber::amount($amount)]),
                $amount,
            );
        }
    }

    #[Test]
    public function it_refuses_floats_in_request_bodies(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Json::encode(['payment' => ['amount' => 0.3]]);
    }

    #[Test]
    public function it_does_not_confuse_texts_with_numbers(): void
    {
        $encoded = Json::encode(['text' => '@number-0@', 'amount' => JsonNumber::amount('5.00')]);

        self::assertSame('{"text":"@number-0@","amount":5}', $encoded);
    }

    #[Test]
    public function it_accepts_only_plain_json_numbers(): void
    {
        foreach (['1e3', '01', '1.', '.5', '1,5', ' 1'] as $literal) {
            try {
                new JsonNumber($literal);
                self::fail(sprintf('"%s" was accepted', $literal));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * Same flags as Guzzle's "json" option used by 2.x: slashes and non-ASCII characters are escaped.
     */
    #[Test]
    public function it_escapes_like_guzzle(): void
    {
        $data = ['text' => 'Società 1/2'];
        $encoded = Json::encode($data);

        self::assertSame(json_encode($data, JSON_THROW_ON_ERROR), $encoded);
        self::assertStringNotContainsString('à', $encoded, 'Non-ASCII characters are escaped');
        self::assertStringContainsString('1\\/2', $encoded, 'Slashes are escaped');
    }

    #[Test]
    public function it_adds_a_query_value_only_when_missing(): void
    {
        self::assertSame('https://x.test/p?a=1&b=2', Url::withQueryValue('https://x.test/p?a=1', 'b', '2'));
        self::assertSame('https://x.test/p?b=2', Url::withQueryValue('https://x.test/p', 'b', '2'));
        self::assertSame('https://x.test/p?b=1', Url::withQueryValue('https://x.test/p?b=1', 'b', '2'));
        self::assertNull(Url::withQueryValue(null, 'b', '2'));
    }
}
