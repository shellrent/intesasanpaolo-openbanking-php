<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Legacy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Models\Transaction;

#[CoversClass(Transaction::class)]
final class TransactionTest extends LegacyTestCase
{
    #[Test]
    public function it_hydrates_an_accounted_transaction(): void
    {
        $transaction = new Transaction(self::decoded([
            'uniqueId' => 'TX-0001',
            'date' => '20171231',
            'reference' => 'REF-0001',
            'amount' => -125.5,
            'currency' => 'EUR',
            'originalAmount' => -125.5,
            'originalCurrency' => 'EUR',
            'exchangeRate' => 1,
            'type' => 'BONIFICO',
            'description' => 'Bonifico a favore di Example Srl',
            'additionalInfo' => 'Fattura 1/2017',
            'accountingDate' => '20171230',
            'valueDate' => '20171229',
            'status' => 'accounted',
        ]));

        self::assertSame('TX-0001', $transaction->getUniqueId());
        self::assertSame('REF-0001', $transaction->getReference());
        self::assertSame('2017-12-31', $transaction->getDate()?->format('Y-m-d'));
        self::assertSame('2017-12-30', $transaction->getAccountingDate()?->format('Y-m-d'));
        self::assertSame('2017-12-29', $transaction->getValueDate()?->format('Y-m-d'));
        self::assertSame(-125.5, $transaction->getAmount());
        self::assertSame('EUR', $transaction->getCurrency());
        self::assertSame(-125.5, $transaction->getOriginalAmount());
        self::assertSame('EUR', $transaction->getOriginalCurrency());
        self::assertSame(1.0, $transaction->getExchangeRate());
        self::assertSame('BONIFICO', $transaction->getType());
        self::assertSame('Bonifico a favore di Example Srl', $transaction->getDescription());
        self::assertSame('Fattura 1/2017', $transaction->getReason());
        self::assertSame('accounted', $transaction->getStatus());
    }

    /**
     * Q-23: unaccounted (intraday) transactions have no uniqueId, date, accountingDate or additionalInfo.
     */
    #[Test]
    public function it_hydrates_an_unaccounted_transaction(): void
    {
        $transaction = new Transaction(self::decoded([
            'reference' => 'REF-0002',
            'amount' => 10,
            'currency' => 'EUR',
            'originalAmount' => 10,
            'originalCurrency' => 'EUR',
            'exchangeRate' => 1,
            'type' => 'POS',
            'description' => 'Pagamento POS',
            'valueDate' => '20171231',
            'status' => 'unaccounted',
        ]));

        self::assertNull($transaction->getUniqueId());
        self::assertNull($transaction->getDate());
        self::assertNull($transaction->getAccountingDate());
        self::assertNull($transaction->getReason());
        self::assertSame('2017-12-31', $transaction->getValueDate()?->format('Y-m-d'));
        self::assertSame('unaccounted', $transaction->getStatus());
    }
}
