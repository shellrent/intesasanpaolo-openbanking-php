<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Response\Sct;

use DateTimeImmutable;
use Shellrent\OpenBanking\Enum\PaymentStatus;
use Shellrent\OpenBanking\Response\Party;
use Shellrent\OpenBanking\Support\EndToEndId;
use Shellrent\OpenBanking\Support\Payload;

/**
 * SCT payment in the payment list.
 */
final readonly class SctPaymentListItem
{
    /**
     * @param  PaymentStatus|null  $paymentStatus  Status, or null for an unknown value: see $rawPaymentStatus.
     * @param  string|null  $remittanceInformation  Present only for remittances (docs/api-quirks.md, Q-24).
     */
    public function __construct(
        public ?string $customerCro,
        public ?string $paymentId,
        public ?PaymentStatus $paymentStatus,
        public ?string $rawPaymentStatus,
        public ?float $amount,
        public ?string $currency,
        public ?DateTimeImmutable $executionDate,
        public ?DateTimeImmutable $valueDate,
        public ?DateTimeImmutable $settlementDate,
        public ?string $endToEndId,
        public ?string $simulationId,
        public Party $debtor,
        public Party $creditor,
        public ?string $remittanceInformation,
        public ?string $categoryPurpose,
    ) {}

    /**
     * @internal
     */
    public static function fromPayload(Payload $payload): self
    {
        $rawStatus = $payload->string('paymentStatus', 'status');

        return new self(
            customerCro: $payload->string('customerCRO', 'customerCro'),
            paymentId: $payload->string('paymentId', 'payment-id'),
            paymentStatus: PaymentStatus::fromApi($rawStatus),
            rawPaymentStatus: $rawStatus,
            amount: $payload->float('amount'),
            currency: $payload->string('currency'),
            executionDate: SctDates::read($payload, 'executionDate'),
            valueDate: SctDates::read($payload, 'valueDate'),
            settlementDate: SctDates::read($payload, 'settlementDate'),
            endToEndId: EndToEndId::normalize($payload->string('endToEndId')),
            simulationId: $payload->string('simulationId'),
            debtor: Party::fromPayload($payload, 'debtor'),
            creditor: Party::fromPayload($payload, 'creditor'),
            remittanceInformation: $payload->string('remittanceInformation'),
            categoryPurpose: $payload->string('categoryPurpose'),
        );
    }
}
