<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Enum\TransactionStatus;
use Shellrent\OpenBanking\Tests\Unit\TestCase;

/**
 * D-11: statuses are matched tolerantly and unknown values give null.
 */
#[CoversClass(TransactionStatus::class)]
final class TransactionStatusTest extends TestCase
{
    #[Test]
    #[DataProvider('values')]
    public function it_reads_api_values(?string $value, ?TransactionStatus $expected): void
    {
        self::assertSame($expected, TransactionStatus::fromApi($value));
    }

    /**
     * @return iterable<string, array{string|null, TransactionStatus|null}>
     */
    public static function values(): iterable
    {
        yield 'exact value' => ['accounted', TransactionStatus::Accounted];
        yield 'different case and spaces' => ['  Reversed ', TransactionStatus::Reversed];
        yield 'description after the code' => ['canceled by customer', TransactionStatus::Canceled];
        yield 'british spelling' => ['cancelled', TransactionStatus::Canceled];
        yield 'unaccounted' => ['unaccounted', TransactionStatus::Unaccounted];
        yield 'unknown value' => ['pending', null];
        yield 'empty value' => ['', null];
        yield 'no value' => [null, null];
    }
}
