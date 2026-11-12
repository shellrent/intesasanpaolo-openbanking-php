<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Response\Iban;

use Shellrent\OpenBanking\Http\ApiResponse;

/**
 * Formal (syntactic) check of an IBAN (GET /tools/iban/formal-check).
 *
 * It says nothing about the existence of the account: see IbanToolsApi::verify().
 */
final readonly class IbanFormalCheck
{
    /**
     * The parts of the IBAN are strings, although the spec declares some of them as numbers: they have
     * leading zeros or letters, e.g. "03069" (docs/api-quirks.md, Q-19).
     *
     * @param  bool|null  $valid  Outcome of the check; null when the API did not say.
     * @param  string|null  $transactionId  "X-ISP-Transaction-ID" of the response, for the ISP support.
     */
    public function __construct(
        public ?bool $valid,
        public ?string $countryCode,
        public ?string $checkDigits,
        public ?string $bankCode,
        public ?string $bankAgency,
        public ?string $account,
        public ?string $bban,
        public ?string $transactionId = null,
    ) {}

    public function isValid(): bool
    {
        return $this->valid === true;
    }

    /**
     * @internal
     */
    public static function fromResponse(ApiResponse $response): self
    {
        $payload = $response->requirePayload();

        return new self(
            valid: $payload->bool('isValid', 'valid'),
            countryCode: $payload->string('countryCode'),
            checkDigits: $payload->string('checkDigits'),
            bankCode: $payload->string('bankCode'),
            bankAgency: $payload->string('bankAgency'),
            account: $payload->string('account'),
            bban: $payload->string('bban'),
            transactionId: $response->transactionId,
        );
    }
}
