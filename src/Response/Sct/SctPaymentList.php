<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Response\Sct;

use DateTimeImmutable;
use Shellrent\OpenBanking\Support\DateFormat;
use Shellrent\OpenBanking\Support\Payload;

/**
 * SCT payments of a date range, with all the pages already collected.
 */
final readonly class SctPaymentList
{
    /**
     * @param  list<SctPaymentListItem>  $payments
     * @param  int|null  $totalCount  Number of payments declared by the API.
     * @param  DateTimeImmutable|null  $executionDate  When the list was extracted ("executionInquiry": docs/api-quirks.md, Q-17).
     * @param  list<string>  $transactionIds  "X-ISP-Transaction-ID" of each page, for the ISP support.
     */
    public function __construct(
        public array $payments,
        public ?int $totalCount,
        public ?DateTimeImmutable $inquiryFromDate,
        public ?DateTimeImmutable $inquiryToDate,
        public ?DateTimeImmutable $executionDate,
        public array $transactionIds = [],
    ) {}

    /**
     * Whether all the payments declared by the API were received.
     */
    public function isComplete(): bool
    {
        return $this->totalCount === null || count($this->payments) >= $this->totalCount;
    }

    /**
     * @internal
     *
     * @param  non-empty-list<Payload>  $pages
     */
    public static function fromPages(array $pages): self
    {
        $first = $pages[0];
        $payments = [];
        $transactionIds = [];

        foreach ($pages as $page) {
            foreach ($page->list('paymentsResults') as $item) {
                $payments[] = SctPaymentListItem::fromPayload($item);
            }

            if ($page->transactionId() !== null) {
                $transactionIds[] = $page->transactionId();
            }
        }

        return new self(
            payments: $payments,
            totalCount: $first->int('paymentsTotalCount'),
            // The spec documents "Ymd", the 2.x SDK also handled "d/m/Y".
            inquiryFromDate: $first->date([DateFormat::Compact, DateFormat::Italian], 'inquiryFromDate'),
            inquiryToDate: $first->date([DateFormat::Compact, DateFormat::Italian], 'inquiryToDate'),
            executionDate: $first->dateTime('executionInquiry', 'executionDate'),
            transactionIds: $transactionIds,
        );
    }
}
