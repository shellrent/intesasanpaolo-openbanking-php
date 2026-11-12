<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Response\Accounts;

use DateTimeImmutable;
use Shellrent\OpenBanking\Support\DateFormat;
use Shellrent\OpenBanking\Support\Payload;

/**
 * Account transactions of a day, with all the pages already collected.
 *
 * Account data and balances come from the first page; the transactions from every page.
 */
final readonly class TransactionList
{
    /**
     * @param  list<Transaction>  $accounted  Accounted transactions.
     * @param  list<Transaction>  $unaccounted  Transactions not yet accounted (intraday only).
     * @param  int|null  $totalAccounted  Number of accounted transactions declared by the API.
     * @param  int|null  $totalUnaccounted  Number of unaccounted transactions declared by the API (intraday only).
     * @param  list<string>  $transactionIds  "X-ISP-Transaction-ID" of each page, for the ISP support.
     */
    public function __construct(
        public array $accounted,
        public array $unaccounted,
        public ?int $totalAccounted,
        public ?int $totalUnaccounted,
        public ?DateTimeImmutable $inquiryDate,
        public ?DateTimeImmutable $executionDate,
        public ?string $iban,
        public ?string $bankCode,
        public ?string $customerCode,
        public ?string $siaCode,
        public ?string $accountType,
        public ?string $accountCurrency,
        public ?float $initialAccountingBalance,
        public ?float $finalAccountingBalance,
        public ?float $liquidBalance,
        public ?float $availableBalance,
        public ?float $creditLine,
        public array $transactionIds = [],
    ) {}

    /**
     * Whether all the accounted transactions declared by the API were received.
     *
     * Always check it before treating the list as the full movement of the day.
     */
    public function isComplete(): bool
    {
        return $this->totalAccounted === null || count($this->accounted) >= $this->totalAccounted;
    }

    /**
     * Builds the list from the payloads of all the pages, in order.
     *
     * @internal
     *
     * @param  non-empty-list<Payload>  $pages
     */
    public static function fromPages(array $pages): self
    {
        $first = $pages[0];
        $accounted = [];
        $unaccounted = [];
        $transactionIds = [];

        foreach ($pages as $page) {
            foreach ($page->list('accountedTransactions') as $item) {
                $accounted[] = Transaction::fromPayload($item);
            }

            foreach ($page->list('unaccountedTransactions') as $item) {
                $unaccounted[] = Transaction::fromPayload($item);
            }

            if ($page->transactionId() !== null) {
                $transactionIds[] = $page->transactionId();
            }
        }

        return new self(
            accounted: $accounted,
            unaccounted: $unaccounted,
            totalAccounted: $first->int('numTotalAccountedTransactions'),
            totalUnaccounted: $first->int('numUnaccountedTransactions'),
            inquiryDate: $first->date(DateFormat::Compact, 'inquiryDate'),
            executionDate: $first->dateTime('executionDate'),
            iban: $first->string('IBAN', 'iban'),
            bankCode: $first->string('bankCode'),
            customerCode: $first->string('customerCode'),
            siaCode: $first->string('SIACode', 'siaCode'),
            accountType: $first->string('accountType'),
            accountCurrency: $first->string('accountCurrency'),
            initialAccountingBalance: $first->float('initialAccountingBalance'),
            finalAccountingBalance: $first->float('finalAccountingBalance'),
            liquidBalance: $first->float('liquidBalance'),
            availableBalance: $first->float('availableBalance'),
            creditLine: $first->float('creditLine'),
            transactionIds: $transactionIds,
        );
    }
}
