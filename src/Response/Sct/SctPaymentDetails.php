<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Response\Sct;

use DateTimeImmutable;
use Shellrent\OpenBanking\Enum\PaymentStatus;
use Shellrent\OpenBanking\Http\ApiResponse;
use Shellrent\OpenBanking\Response\Party;
use Shellrent\OpenBanking\Support\EndToEndId;

/**
 * Status of an SCT payment (GET /payments/bonsct/{iban}/history).
 */
final readonly class SctPaymentDetails
{
    /**
     * @param  PaymentStatus|null  $paymentStatus  Status, or null for an unknown value: see $rawPaymentStatus.
     * @param  string|null  $transactionId  "X-ISP-Transaction-ID" of the response, for the ISP support.
     */
    public function __construct(
        public ?string $paymentId,
        public ?string $endToEndId,
        public ?PaymentStatus $paymentStatus,
        public ?string $rawPaymentStatus,
        public ?float $amount,
        public ?string $currency,
        public ?DateTimeImmutable $executionDate,
        public ?DateTimeImmutable $valueDate,
        public ?DateTimeImmutable $settlementDate,
        public ?string $paymentInformation,
        public Party $debtor,
        public Party $creditor,
        public ?string $transactionId = null,
    ) {}

    /**
     * @internal
     */
    public static function fromResponse(ApiResponse $response): self
    {
        $payload = $response->requirePayload();
        $rawStatus = $payload->string('paymentStatus', 'status');

        return new self(
            paymentId: $payload->string('paymentId', 'payment-id'),
            endToEndId: EndToEndId::normalize($payload->string('endToEndId')),
            paymentStatus: PaymentStatus::fromApi($rawStatus),
            rawPaymentStatus: $rawStatus,
            amount: $payload->float('amount'),
            currency: $payload->string('currency'),
            executionDate: SctDates::read($payload, 'executionDate'),
            valueDate: SctDates::read($payload, 'valueDate'),
            settlementDate: SctDates::read($payload, 'settlementDate'),
            paymentInformation: $payload->string('paymentInformation'),
            debtor: Party::fromPayload($payload, 'debtor'),
            creditor: Party::fromPayload($payload, 'creditor'),
            transactionId: $response->transactionId,
        );
    }
}
