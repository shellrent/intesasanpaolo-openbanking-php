<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Response\SctInstant;

use Shellrent\OpenBanking\Enum\InstantStatus;
use Shellrent\OpenBanking\Http\ApiResponse;
use Shellrent\OpenBanking\Response\Party;
use Shellrent\OpenBanking\Support\EndToEndId;

/**
 * Status of an SCT Instant payment (GET /payments/sct/instant/{IBAN}/history/{orderId}).
 */
final readonly class InstantPaymentDetails
{
    /**
     * @param  InstantStatus|null  $status  Status, or null for an unknown value: see $rawStatus.
     * @param  string|null  $transactionId  "X-ISP-Transaction-ID" of the response, for the ISP support.
     */
    public function __construct(
        public ?string $orderId,
        public ?string $paymentId,
        public ?string $endToEndId,
        public ?InstantStatus $status,
        public ?string $rawStatus,
        public ?string $amount,
        public ?string $currency,
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
        $rawStatus = $payload->string('status', 'paymentStatus');

        return new self(
            orderId: $payload->string('orderId', 'order-id'),
            paymentId: $payload->string('paymentId', 'payment-id'),
            endToEndId: EndToEndId::normalize($payload->string('endToEndId')),
            status: InstantStatus::fromApi($rawStatus),
            rawStatus: $rawStatus,
            amount: $payload->amount('amount'),
            currency: $payload->string('currency'),
            paymentInformation: $payload->string('paymentInformation'),
            debtor: Party::fromPayload($payload, 'debtor'),
            creditor: Party::fromPayload($payload, 'creditor'),
            transactionId: $response->transactionId,
        );
    }
}
