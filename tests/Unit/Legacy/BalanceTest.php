<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Legacy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Models\Balance;
use Shellrent\OpenBanking\Models\GenericModel;

#[CoversClass(Balance::class)]
#[CoversClass(GenericModel::class)]
final class BalanceTest extends LegacyTestCase
{
    #[Test]
    public function it_hydrates_a_balance_response(): void
    {
        $balance = new Balance(self::decoded([
            'success' => true,
            'errors' => null,
            'payload' => [
                'currency' => 'EUR',
                'availableBalance' => 1500.75,
                'accountingBalance' => 1450.25,
                'creditLine' => 0,
                'inquiryDate' => '20180131',
                'executionDate' => '2018-05-04T09:52:10.289+0200',
            ],
        ]));

        self::assertSame('EUR', $balance->getCurrency());
        self::assertSame(1500.75, $balance->getAvailableBalance());
        self::assertSame(1450.25, $balance->getAccountingBalance());
        self::assertSame(0.0, $balance->getCreditLine());
        self::assertSame('2018-01-31 00:00:00', $balance->getInquiryDate()?->format('Y-m-d H:i:s'));
        self::assertSame('2018-05-04T09:52:10+02:00', $balance->getExecutionDate()?->format(DATE_ATOM));
    }

    /**
     * Q-21: every balance field may be null or missing.
     */
    #[Test]
    public function it_accepts_null_and_missing_fields(): void
    {
        $balance = new Balance(self::decoded([
            'success' => true,
            'errors' => null,
            'payload' => [
                'availableBalance' => null,
            ],
        ]));

        self::assertNull($balance->getCurrency());
        self::assertNull($balance->getAvailableBalance());
        self::assertNull($balance->getAccountingBalance());
        self::assertNull($balance->getCreditLine());
        self::assertNull($balance->getInquiryDate());
        self::assertNull($balance->getExecutionDate());
    }
}
