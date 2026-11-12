<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Response\SctInstant;

use DateTimeImmutable;
use Shellrent\OpenBanking\Support\DateFormat;
use Shellrent\OpenBanking\Support\Payload;

/**
 * SCT Instant payments of a date range, with all the pages already collected.
 */
final readonly class InstantPaymentList
{
    /**
     * @param  list<InstantPaymentListItem>  $payments
     * @param  int|null  $totalCount  Number of payments declared by the API.
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
            foreach ($page->list('payments') as $item) {
                $payments[] = InstantPaymentListItem::fromPayload($item);
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
            executionDate: $first->dateTime('executionDate'),
            transactionIds: $transactionIds,
        );
    }
}
