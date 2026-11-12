<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Response\Sct;

use Shellrent\OpenBanking\Http\ApiResponse;

/**
 * Result of an SCT revoke request (GET /payments/bonsct/{iban}/revoke, docs/api-quirks.md, Q-31 and Q-40).
 */
final readonly class SctRevocation
{
    /**
     * @param  bool|null  $revoked  True for "OK", false for "KO", null for an unknown value: see $rawStatus.
     * @param  string|null  $transactionId  "X-ISP-Transaction-ID" of the response, for the ISP support.
     */
    public function __construct(
        public ?bool $revoked,
        public ?string $rawStatus,
        public ?string $paymentId,
        public ?string $customerCro,
        public ?string $transactionId = null,
    ) {}

    /**
     * Whether the bank confirmed the revocation.
     */
    public function isRevoked(): bool
    {
        return $this->revoked === true;
    }

    /**
     * @internal
     */
    public static function fromResponse(ApiResponse $response): self
    {
        $payload = $response->requirePayload();
        $rawStatus = $payload->string('status');

        return new self(
            revoked: match (strtoupper(trim((string) $rawStatus))) {
                'OK' => true,
                'KO' => false,
                default => null,
            },
            rawStatus: $rawStatus,
            paymentId: $payload->string('paymentId', 'payment-id'),
            customerCro: $payload->string('customerCRO', 'customerCro'),
            transactionId: $response->transactionId,
        );
    }
}
