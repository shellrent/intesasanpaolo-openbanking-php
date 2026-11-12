<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Response\SctInstant;

use DateTimeImmutable;
use Shellrent\OpenBanking\Enum\PaymentStatus;
use Shellrent\OpenBanking\Http\ApiResponse;
use Shellrent\OpenBanking\Response\Party;
use Shellrent\OpenBanking\Support\EndToEndId;

/**
 * Result of an SCT Instant execution or resubmission (POST /payments/sct/instant).
 */
final readonly class InstantExecution
{
    /**
     * @param  string|null  $orderId  Identifier of the payment, used by the status API.
     * @param  string|null  $resubmitId  Identifier to resubmit a pending (PNDG) payment.
     * @param  string|null  $customerCro  Unique identifier (CRO) of the payment.
     * @param  PaymentStatus|null  $paymentStatus  Status, or null for an unknown value: see $rawPaymentStatus.
     * @param  string|null  $transactionId  "X-ISP-Transaction-ID" of the response, for the ISP support.
     */
    public function __construct(
        public ?string $orderId,
        public ?string $paymentId,
        public ?string $customerCro,
        public ?string $endToEndId,
        public ?string $resubmitId,
        public ?PaymentStatus $paymentStatus,
        public ?string $rawPaymentStatus,
        public ?string $transactionStatusDescription,
        public ?float $amount,
        public ?string $currency,
        public ?DateTimeImmutable $date,
        public ?string $categoryPurpose,
        public ?string $paymentInformation,
        public Party $debtor,
        public Party $creditor,
        public ?string $transactionId = null,
    ) {}

    /**
     * Whether the payment is pending and can be resubmitted with SctInstantApi::resubmit() (docs/api-quirks.md, Q-36).
     */
    public function canBeResubmitted(): bool
    {
        return $this->paymentStatus === PaymentStatus::Pending && $this->resubmitId !== null;
    }

    /**
     * @internal
     */
    public static function fromResponse(ApiResponse $response): self
    {
        $payload = $response->requirePayload();
        $rawStatus = $payload->string('paymentStatus');

        return new self(
            orderId: $payload->string('orderId', 'order-id'),
            paymentId: $payload->string('paymentId', 'payment-id'),
            customerCro: $payload->string('customerCro', 'customerCRO'),
            endToEndId: EndToEndId::normalize($payload->string('endToEndId')),
            resubmitId: $payload->string('resubmitId'),
            paymentStatus: PaymentStatus::fromApi($rawStatus),
            rawPaymentStatus: $rawStatus,
            transactionStatusDescription: $payload->string('transactionStatusDescription'),
            amount: $payload->float('amount'),
            currency: $payload->string('currency'),
            date: $payload->dateTime('date'),
            categoryPurpose: $payload->string('categoryPurpose'),
            paymentInformation: $payload->string('paymentInformation'),
            debtor: Party::fromPayload($payload, 'debtor'),
            creditor: Party::fromPayload($payload, 'creditor'),
            transactionId: $response->transactionId,
        );
    }
}
