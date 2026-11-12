<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Response\Sct;

use DateTimeImmutable;
use Shellrent\OpenBanking\Enum\PaymentStatus;
use Shellrent\OpenBanking\Http\ApiResponse;
use Shellrent\OpenBanking\Response\Party;

/**
 * Result of an SCT execution (POST /payments/bonsct).
 */
final readonly class SctExecution
{
    /**
     * @param  string|null  $customerCro  Unique identifier (CRO) of the payment, used by status and revoke.
     * @param  PaymentStatus|null  $paymentStatus  ACSP, ACTC, VRFY or RJCT; null for an unknown value: see $rawPaymentStatus.
     * @param  Party  $creditor  Only the creditor BIC is returned by this API.
     * @param  float|null  $fees  Fees of the payment ("fees" or "commissioni": docs/api-quirks.md, Q-15).
     * @param  DateTimeImmutable|null  $revokeDate  Revoke deadline, with the time of "revokeTime" when present.
     * @param  string|null  $transactionId  "X-ISP-Transaction-ID" of the response, for the ISP support.
     */
    public function __construct(
        public ?string $customerCro,
        public ?string $paymentId,
        public ?PaymentStatus $paymentStatus,
        public ?string $rawPaymentStatus,
        public ?string $transactionStatusDescription,
        public ?float $amount,
        public ?string $currency,
        public Party $creditor,
        public ?string $creditorAgent,
        public ?float $fees,
        public ?DateTimeImmutable $executionDate,
        public ?DateTimeImmutable $valueDate,
        public ?DateTimeImmutable $settlementDate,
        public ?DateTimeImmutable $revokeDate,
        public ?string $revokeTime,
        public ?string $transactionId = null,
    ) {}

    /**
     * @internal
     */
    public static function fromResponse(ApiResponse $response): self
    {
        $payload = $response->requirePayload();
        $rawStatus = $payload->string('paymentStatus');

        return new self(
            customerCro: $payload->string('customerCRO', 'customerCro'),
            paymentId: $payload->string('paymentId', 'payment-id'),
            paymentStatus: PaymentStatus::fromApi($rawStatus),
            rawPaymentStatus: $rawStatus,
            transactionStatusDescription: $payload->string('transactionStatusDescription'),
            amount: $payload->float('amount'),
            currency: $payload->string('currency'),
            creditor: Party::fromPayload($payload, 'creditor'),
            creditorAgent: $payload->string('creditorAgent'),
            fees: $payload->float('fees', 'commissioni'),
            executionDate: SctDates::read($payload, 'executionDate'),
            valueDate: SctDates::read($payload, 'valueDate'),
            settlementDate: SctDates::read($payload, 'settlementDate'),
            revokeDate: SctDates::revokeDate($payload),
            revokeTime: $payload->string('revokeTime'),
            transactionId: $response->transactionId,
        );
    }
}
