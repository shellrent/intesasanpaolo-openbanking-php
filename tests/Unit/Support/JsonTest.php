<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Support\Json;
use Shellrent\OpenBanking\Support\Url;
use Shellrent\OpenBanking\Tests\Unit\TestCase;

#[CoversClass(Json::class)]
#[CoversClass(Url::class)]
final class JsonTest extends TestCase
{
    /**
     * F-36: amounts are encoded exactly whatever the "serialize_precision" setting.
     */
    #[Test]
    public function it_encodes_floats_independently_of_serialize_precision(): void
    {
        $original = ini_get('serialize_precision');
        self::assertIsString($original);

        try {
            ini_set('serialize_precision', '17');

            self::assertSame('{"amount":0.3,"fees":150.25,"round":10}', Json::encode(['amount' => 0.3, 'fees' => 150.25, 'round' => 10.0]));
            self::assertSame('17', ini_get('serialize_precision'), 'The setting is restored');
        } finally {
            ini_set('serialize_precision', $original);
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
