<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Response\SctInstant;

use Shellrent\OpenBanking\Http\ApiResponse;

/**
 * Whether an IBAN can receive SCT Instant payments, i.e. whether its bank joins the scheme
 * (GET /payments/sct/instant/reachability, docs/api-quirks.md, Q-18).
 */
final readonly class InstantReachability
{
    /**
     * @param  bool|null  $reachable  Outcome; null when the API did not say.
     * @param  string|null  $status  Message of the bank about the reachability ("IBAN_Status").
     * @param  string|null  $bic  BIC of the bank of the IBAN.
     * @param  string|null  $transactionId  "X-ISP-Transaction-ID" of the response, for the ISP support.
     */
    public function __construct(
        public ?bool $reachable,
        public ?string $status,
        public ?string $bic,
        public ?string $transactionId = null,
    ) {}

    public function isReachable(): bool
    {
        return $this->reachable === true;
    }

    /**
     * @internal
     */
    public static function fromResponse(ApiResponse $response): self
    {
        $payload = $response->requirePayload();

        return new self(
            reachable: $payload->bool('isReachable', 'reachable'),
            status: $payload->string('IBAN_Status', 'ibanStatus'),
            bic: $payload->string('BIC', 'bic'),
            transactionId: $response->transactionId,
        );
    }
}
