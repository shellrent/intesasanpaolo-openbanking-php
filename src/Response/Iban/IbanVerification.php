<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Response\Iban;

use Shellrent\OpenBanking\Http\ApiResponse;

/**
 * Existence of an IBAN and, when a fiscal code or VAT number is given, its consistency with the
 * account holder (GET /tools/iban/chargeabilityMulti, docs/api-quirks.md, Q-20).
 *
 * Covers Intesa Sanpaolo accounts and the accounts of the banks joining the CBI Globe "Check IBAN" service.
 */
final readonly class IbanVerification
{
    /**
     * Additional information for an account moved to other coordinates: OK if it stayed within the
     * Intesa Sanpaolo group, KO if it moved to another bank.
     */
    public const string TRANSFERRED_ACCOUNT = 'AC15';

    /**
     * @param  bool|null  $allowed  Outcome: the IBAN exists and, if requested, matches the holder; null when the API did not say.
     * @param  string|null  $additionalInfo  Additional information, e.g. "AC15" for a transferred account.
     * @param  string|null  $ibanType  Type of IBAN, e.g. "account".
     * @param  string|null  $transactionId  "X-ISP-Transaction-ID" of the response, for the ISP support.
     */
    public function __construct(
        public ?bool $allowed,
        public ?string $additionalInfo,
        public ?string $ibanType,
        public ?string $transactionId = null,
    ) {}

    public function isAllowed(): bool
    {
        return $this->allowed === true;
    }

    /**
     * Whether the account was transferred to other coordinates ("AC15").
     */
    public function isTransferred(): bool
    {
        return $this->additionalInfo !== null && str_contains(strtoupper($this->additionalInfo), self::TRANSFERRED_ACCOUNT);
    }

    /**
     * @internal
     */
    public static function fromResponse(ApiResponse $response): self
    {
        $payload = $response->requirePayload();
        // The spec documents "allowed"; the backend model and the SmartHub page use "isAllowed",
        // even as the string "true" (docs/api-quirks.md, Q-20).
        $allowed = $payload->bool('allowed', 'isAllowed', 'IsAllowed');

        return new self(
            // A positive outcome inside an unsuccessful response is contradictory: never report it as allowed (D-58).
            allowed: $response->unsuccessful && $allowed === true ? null : $allowed,
            additionalInfo: $payload->string('additionalInfo', 'AdditionalInformation'),
            ibanType: $payload->string('ibanType'),
            transactionId: $response->transactionId,
        );
    }
}
