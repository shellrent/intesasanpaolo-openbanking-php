<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Support;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Exception\UnexpectedResponseException;
use Shellrent\OpenBanking\Support\Amount;
use Shellrent\OpenBanking\Support\Decimal;
use Shellrent\OpenBanking\Support\Payload;
use Shellrent\OpenBanking\Tests\Unit\TestCase;
use stdClass;

/**
 * D-65: amounts are exact decimal strings, never floats.
 */
#[CoversClass(Decimal::class)]
#[CoversClass(Amount::class)]
#[CoversClass(Payload::class)]
final class DecimalTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function bankAmounts(): iterable
    {
        yield 'decimal with one digit' => ['14980.1', '14980.10'];
        yield 'decimal with two digits' => ['14980.10', '14980.10'];
        yield 'integer' => ['1250', '1250.00'];
        yield 'zero' => ['0', '0.00'];
        yield 'negative' => ['-525', '-525.00'];
        yield 'one cent' => ['0.01', '0.01'];
        yield 'largest' => ['999999999999.99', '999999999999.99'];
        yield 'string, as the sandbox balance' => ['"250000.00"', '250000.00'];
        yield 'string with trailing zeros' => ['"10.500"', '10.50'];
    }

    /**
     * The raw JSON goes through json_decode(), as in a real response.
     */
    #[Test]
    #[DataProvider('bankAmounts')]
    public function it_reads_the_amounts_of_the_bank_exactly(string $json, string $expected): void
    {
        self::assertSame($expected, $this->payload('{"amount": '.$json.'}')->amount('amount'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function inexactAmounts(): iterable
    {
        yield 'floating-point noise' => ['0.30000000000000004'];
        yield 'three decimals' => ['1.005'];
        yield 'exponent' => ['1e25'];
        yield 'decimal comma in a string' => ['"1,50"'];
        yield 'text' => ['"n/a"'];
        yield 'boolean' => ['true'];
    }

    #[Test]
    #[DataProvider('inexactAmounts')]
    public function it_refuses_amounts_that_are_not_exact_decimals(string $json): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('payload.amount');

        $this->payload('{"amount": '.$json.'}')->amount('amount');
    }

    #[Test]
    public function it_drops_an_inexact_amount_in_lenient_mode(): void
    {
        self::assertNull($this->payload('{"amount": 0.30000000000000004}')->lenient()->amount('amount'));
    }

    #[Test]
    public function it_reads_foreign_amounts_with_more_decimals(): void
    {
        $payload = $this->payload('{"originalAmount": 12.345, "rounded": 12.5}');

        self::assertSame('12.345', $payload->decimal(4, 'originalAmount'));
        self::assertSame('12.50', $payload->decimal(4, 'rounded'));
    }

    #[Test]
    public function it_converts_cents_exactly(): void
    {
        self::assertSame('1250.10', Amount::fromCents(125010));
        self::assertSame('0.05', Amount::fromCents(5));
        self::assertSame('0.00', Amount::fromCents(0));
        self::assertSame('-5.25', Amount::fromCents(-525));
        self::assertSame(125010, Amount::toCents('1250.10'));
        self::assertSame(125000, Amount::toCents('1250'));
        self::assertSame(125010, Amount::toCents('1250.1'));
        self::assertSame(-525, Amount::toCents('-5.25'));
        self::assertSame(99999999999999, Amount::toCents('999999999999.99'));

        $this->expectException(InvalidArgumentException::class);

        Amount::toCents('1.005');
    }

    private function payload(string $json): Payload
    {
        $data = json_decode('{"payload": '.$json.'}', false, 512, JSON_THROW_ON_ERROR);
        self::assertInstanceOf(stdClass::class, $data);
        self::assertInstanceOf(stdClass::class, $data->payload);

        return new Payload($data->payload);
    }
}
