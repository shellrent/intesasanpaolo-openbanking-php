<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Response\SctInstant;

use DateTimeImmutable;
use Shellrent\OpenBanking\Enum\InstantStatus;
use Shellrent\OpenBanking\Response\Party;
use Shellrent\OpenBanking\Support\DateFormat;
use Shellrent\OpenBanking\Support\Payload;

/**
 * SCT Instant payment in the payment list.
 */
final readonly class InstantPaymentListItem
{
    /**
     * @param  InstantStatus|null  $status  Status, or null for an unknown value: see $rawStatus.
     * @param  string|null  $remittanceInformation  Present only for remittances (docs/api-quirks.md, Q-24).
     */
    public function __construct(
        public ?string $orderId,
        public ?string $paymentId,
        public ?InstantStatus $status,
        public ?string $rawStatus,
        public ?string $amount,
        public Party $debtor,
        public Party $creditor,
        public ?string $remittanceInformation,
        public ?DateTimeImmutable $valueDate,
        public ?string $categoryPurpose,
    ) {}

    /**
     * @internal
     */
    public static function fromPayload(Payload $payload): self
    {
        $rawStatus = $payload->string('status', 'paymentStatus');

        return new self(
            orderId: $payload->string('orderId', 'order-id'),
            paymentId: $payload->string('paymentId', 'payment-id'),
            status: InstantStatus::fromApi($rawStatus),
            rawStatus: $rawStatus,
            amount: $payload->amount('amount'),
            debtor: Party::fromPayload($payload, 'debtor'),
            creditor: Party::fromPayload($payload, 'creditor'),
            remittanceInformation: $payload->string('remittanceInformation'),
            valueDate: $payload->date([DateFormat::Italian, DateFormat::Compact], 'valueDate'),
            categoryPurpose: $payload->string('categoryPurpose'),
        );
    }
}
