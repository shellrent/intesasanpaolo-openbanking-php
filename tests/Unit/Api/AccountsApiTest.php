<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Api;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Api\AccountsApi;
use Shellrent\OpenBanking\Config\Environment;
use Shellrent\OpenBanking\Enum\TransactionStatus;
use Shellrent\OpenBanking\Exception\UnexpectedResponseException;
use Shellrent\OpenBanking\IntesaSanPaoloClient;
use Shellrent\OpenBanking\Response\Accounts\Balance;
use Shellrent\OpenBanking\Response\Accounts\Transaction;
use Shellrent\OpenBanking\Response\Accounts\TransactionList;
use Shellrent\OpenBanking\Tests\Support\MockHttp;
use Shellrent\OpenBanking\Tests\Unit\TestCase;

#[CoversClass(AccountsApi::class)]
#[CoversClass(Balance::class)]
#[CoversClass(Transaction::class)]
#[CoversClass(TransactionList::class)]
final class AccountsApiTest extends TestCase
{
    private const string BASE = 'https://external-api.intesasanpaolo.com/twa/live/v1/accounts/'.self::IBAN;

    #[Test]
    public function it_reads_todays_balance(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::fixture('Accounts/balance', ['X-ISP-Transaction-ID' => 'TX-B']));

        $balance = $this->accounts($http)->balance();

        self::assertSame(self::BASE.'/balance', (string) $http->request(1)->getUri());
        self::assertSame('EUR', $balance->currency);
        self::assertSame(15230.45, $balance->availableBalance);
        self::assertSame(14980.1, $balance->accountingBalance);
        self::assertSame(0.0, $balance->creditLine);
        self::assertSame('2026-01-14 00:00:00 Europe/Rome', $balance->inquiryDate?->format('Y-m-d H:i:s e'));
        self::assertSame('2026-01-15T09:52:10+01:00', $balance->executionDate?->format(DATE_ATOM));
        self::assertSame('TX-B', $balance->transactionId);
    }

    #[Test]
    public function it_requests_the_balance_of_a_given_date(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::fixture('Accounts/balance'));

        $this->accounts($http)->balance(new DateTimeImmutable('2026-01-14 18:00:00', new DateTimeZone('UTC')));

        self::assertSame(self::BASE.'/balance?date=20260114', (string) $http->request(1)->getUri());
    }

    /**
     * Q-21: every balance field may be missing.
     */
    #[Test]
    public function it_accepts_a_balance_without_values(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::envelope(['availableBalance' => null]));

        $balance = $this->accounts($http)->balance();

        self::assertNull($balance->currency);
        self::assertNull($balance->availableBalance);
        self::assertNull($balance->inquiryDate);
        self::assertNull($balance->executionDate);
    }

    /**
     * Q-41: all the pages are collected, and the required "date" is kept on the following pages.
     */
    #[Test]
    public function it_collects_all_the_pages_of_the_end_of_day_transactions(): void
    {
        $http = new MockHttp(
            MockHttp::token(),
            MockHttp::fixture('Accounts/transactions-end-of-day-page-1', ['X-ISP-Transaction-ID' => 'TX-P1']),
            MockHttp::fixture('Accounts/transactions-end-of-day-page-2', ['X-ISP-Transaction-ID' => 'TX-P2']),
        );

        $list = $this->accounts($http)->transactions(new DateTimeImmutable('2026-01-14'));

        self::assertSame(self::BASE.'/transactions?date=20260114', (string) $http->request(1)->getUri());
        self::assertSame(self::BASE.'/transactions?offsetid=TkVYVDE=&date=20260114', (string) $http->request(2)->getUri());
        self::assertSame(3, $http->count());

        self::assertSame(['TX-0001', 'TX-0002', 'TX-0003'], array_map(static fn (Transaction $t): ?string => $t->uniqueId, $list->accounted));
        self::assertSame([], $list->unaccounted);
        self::assertSame(3, $list->totalAccounted);
        self::assertNull($list->totalUnaccounted);
        self::assertTrue($list->isComplete());
        self::assertSame(['TX-P1', 'TX-P2'], $list->transactionIds);

        self::assertSame('2026-01-14', $list->inquiryDate?->format('Y-m-d'));
        self::assertSame('2026-01-15T07:30:00+00:00', $list->executionDate?->format(DATE_ATOM));
        self::assertSame(self::IBAN, $list->iban);
        self::assertSame('03069', $list->bankCode);
        self::assertSame('AB123', $list->siaCode);
        self::assertSame('CC', $list->accountType);
        self::assertSame('EUR', $list->accountCurrency);
        self::assertSame(10000.0, $list->initialAccountingBalance);
        self::assertSame(9850.5, $list->finalAccountingBalance);
        self::assertSame(9850.5, $list->liquidBalance);
        self::assertSame(9700.25, $list->availableBalance);
        self::assertSame(0.0, $list->creditLine);
    }

    #[Test]
    public function it_reads_every_field_of_a_transaction(): void
    {
        $http = new MockHttp(
            MockHttp::token(),
            MockHttp::fixture('Accounts/transactions-end-of-day-page-1'),
            MockHttp::fixture('Accounts/transactions-end-of-day-page-2'),
        );

        [$debit, $reversed] = $this->accounts($http)->transactions(new DateTimeImmutable('2026-01-14'))->accounted;

        self::assertSame('TX-0001', $debit->uniqueId);
        self::assertSame('REF-0001', $debit->reference);
        self::assertSame(-149.5, $debit->amount);
        self::assertSame('EUR', $debit->currency);
        self::assertSame(-149.5, $debit->originalAmount);
        self::assertSame('EUR', $debit->originalCurrency);
        self::assertSame(1.0, $debit->exchangeRate);
        self::assertSame('BONIFICO', $debit->type);
        self::assertSame('Bonifico a favore di Example Srl', $debit->description);
        self::assertSame('Fattura 1/2026', $debit->additionalInfo);
        self::assertSame('2026-01-14', $debit->date?->format('Y-m-d'));
        self::assertSame('2026-01-14', $debit->accountingDate?->format('Y-m-d'));
        self::assertSame('2026-01-13', $debit->valueDate?->format('Y-m-d'));
        self::assertSame(TransactionStatus::Accounted, $debit->status);
        self::assertSame('accounted', $debit->rawStatus);

        self::assertSame('20260002', $reversed->reference);
        self::assertSame(250.0, $reversed->amount);
        self::assertNull($reversed->originalAmount);
        self::assertNull($reversed->additionalInfo);
        self::assertSame(TransactionStatus::Reversed, $reversed->status);
    }

    #[Test]
    public function it_does_not_duplicate_a_date_already_present_in_the_next_link(): void
    {
        $http = new MockHttp(
            MockHttp::token(),
            MockHttp::envelope([
                'numTotalAccountedTransactions' => 0,
                'nextPage' => self::BASE.'/transactions?date=20260114&offsetid=abc',
                'accountedTransactions' => [],
            ]),
            MockHttp::envelope(['accountedTransactions' => []]),
        );

        $this->accounts($http)->transactions(new DateTimeImmutable('2026-01-14'));

        self::assertSame(self::BASE.'/transactions?date=20260114&offsetid=abc', (string) $http->request(2)->getUri());
    }

    /**
     * Q-23: unaccounted transactions lack the accounted-only fields, and a value date of "" or "0" is null.
     */
    #[Test]
    public function it_reads_todays_accounted_and_unaccounted_transactions(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::fixture('Accounts/transactions-today'));

        $list = $this->accounts($http)->todayTransactions();

        self::assertSame(self::BASE.'/transactions/today', (string) $http->request(1)->getUri());
        self::assertSame(2, $http->count());

        self::assertCount(1, $list->accounted);
        self::assertSame(TransactionStatus::Accounted, $list->accounted[0]->status, 'Status matching ignores case');
        self::assertSame('ACCOUNTED', $list->accounted[0]->rawStatus);

        self::assertCount(2, $list->unaccounted);
        self::assertSame(2, $list->totalUnaccounted);

        [$first, $second] = $list->unaccounted;
        self::assertNull($first->uniqueId);
        self::assertNull($first->date);
        self::assertNull($first->accountingDate);
        self::assertNull($first->additionalInfo);
        self::assertNull($first->valueDate);
        self::assertNull($second->valueDate);
        self::assertSame(TransactionStatus::Unaccounted, $first->status);
        self::assertSame(-20.0, $first->amount);
    }

    #[Test]
    public function it_follows_the_sandbox_links_of_todays_transactions(): void
    {
        $http = new MockHttp(
            MockHttp::token(),
            MockHttp::envelope([
                'nextPage' => 'https://external-api.syssede.systest.sanpaoloimi.com/twa/sandbox/v1/accounts/'.self::IBAN.'/transactions/today?offsetid=p2',
                'accountedTransactions' => [],
            ]),
            MockHttp::envelope(['accountedTransactions' => []]),
        );

        $this->accounts($http, Environment::Sandbox)->todayTransactions();

        self::assertSame(
            'https://external-api.intesasanpaolo.com/twa/sandbox/v1/accounts/'.self::IBAN.'/transactions/today?offsetid=p2',
            (string) $http->request(2)->getUri(),
        );
    }

    #[Test]
    public function it_reports_an_incomplete_list(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::envelope([
            'numTotalAccountedTransactions' => 5,
            'accountedTransactions' => [['uniqueId' => 'TX-1', 'amount' => 1]],
        ]));

        self::assertFalse($this->accounts($http)->transactions(new DateTimeImmutable('2026-01-14'))->isComplete());
    }

    /**
     * Unlike 2.x, which turned a missing amount into 0.0, a transaction without amount is an error.
     */
    #[Test]
    public function it_rejects_a_transaction_without_amount(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::envelope([
            'accountedTransactions' => [['uniqueId' => 'TX-1', 'amount' => null]],
        ]));

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('payload.accountedTransactions[0].amount');

        $this->accounts($http)->transactions(new DateTimeImmutable('2026-01-14'));
    }

    private function accounts(MockHttp $http, Environment $environment = Environment::Live): AccountsApi
    {
        return (new IntesaSanPaoloClient($this->config($environment), $http->client))->accounts();
    }
}
