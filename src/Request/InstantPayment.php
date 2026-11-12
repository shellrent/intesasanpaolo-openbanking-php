<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Request;

use Shellrent\OpenBanking\Exception\InvalidRequestException;

/**
 * SCT Instant payment to simulate and execute.
 *
 * The values are validated and normalized on creation; an invalid value raises an InvalidRequestException.
 */
final readonly class InstantPayment
{
    public string $creditorName;

    public string $creditorIban;

    /**
     * Amount in euro, rounded to two decimals.
     */
    public float $amount;

    public string $paymentInformation;

    public string $debtorName;

    /**
     * Debtor IBAN; null means the IBAN of the client configuration.
     */
    public ?string $debtorIban;

    public ?string $endToEndId;

    public ?string $siaCode;

    /**
     * @param  string  $creditorName  Name of the beneficiary (max 70 characters): it is checked by the Verification of Payee.
     * @param  string  $creditorIban  IBAN of the beneficiary.
     * @param  float  $amount  Amount in euro, positive, with at most two decimals.
     * @param  string  $paymentInformation  Payment reason (max 140 characters).
     * @param  string  $debtorName  Name of the payer, i.e. the account holder (max 70 characters).
     * @param  string|null  $debtorIban  IBAN of the payer; null for the IBAN of the client configuration.
     * @param  string|null  $endToEndId  Identifier chosen by the payer (max 35 characters); "" and "NOTPROVIDED" mean none.
     * @param  string|null  $siaCode  SIA code of the company, for CBI customers.
     *
     * @throws InvalidRequestException
     */
    public function __construct(
        string $creditorName,
        string $creditorIban,
        float $amount,
        string $paymentInformation,
        string $debtorName,
        ?string $debtorIban = null,
        ?string $endToEndId = null,
        ?string $siaCode = null,
    ) {
        $this->creditorName = Validator::text($creditorName, 'creditor name', Validator::MAX_NAME_LENGTH);
        $this->creditorIban = Validator::iban($creditorIban, 'creditor IBAN');
        $this->amount = Validator::amount($amount);
        $this->paymentInformation = Validator::text($paymentInformation, 'payment information', Validator::MAX_PAYMENT_INFORMATION_LENGTH);
        $this->debtorName = Validator::text($debtorName, 'debtor name', Validator::MAX_NAME_LENGTH);
        $this->debtorIban = $debtorIban === null ? null : Validator::iban($debtorIban, 'debtor IBAN');
        $this->endToEndId = Validator::endToEndId($endToEndId);
        $this->siaCode = Validator::optional($siaCode);
    }
}
