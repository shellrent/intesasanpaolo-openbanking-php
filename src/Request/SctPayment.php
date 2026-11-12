<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Request;

use DateTimeImmutable;
use DateTimeInterface;
use Shellrent\OpenBanking\Exception\InvalidRequestException;

/**
 * SCT (bonifico ordinario) payment to simulate and execute.
 *
 * The values are validated and normalized on creation; an invalid value raises an InvalidRequestException.
 */
final readonly class SctPayment
{
    /**
     * Category purpose sent by the 2.x SDK for every payment.
     */
    public const string DEFAULT_CATEGORY_PURPOSE = 'CASH';

    /**
     * SEPA credit transfers are in euro.
     */
    public const string DEFAULT_CURRENCY = 'EUR';

    public string $creditorName;

    public string $creditorIban;

    /**
     * Amount, rounded to two decimals.
     */
    public float $amount;

    public string $paymentInformation;

    public string $debtorName;

    /**
     * Debtor IBAN; null means the IBAN of the client configuration.
     */
    public ?string $debtorIban;

    /**
     * Requested execution date (calendar date); null leaves it to the bank.
     */
    public ?DateTimeImmutable $requestedExecutionDate;

    public ?string $endToEndId;

    /**
     * ISO 20022 category purpose code, e.g. CASH, SUPP (supplier payment), SALA (salary).
     */
    public string $categoryPurpose;

    public string $currency;

    /**
     * @param  string  $creditorName  Name of the beneficiary (max 70 characters): it is checked by the Verification of Payee.
     * @param  string  $creditorIban  IBAN of the beneficiary.
     * @param  float  $amount  Amount, positive, with at most two decimals.
     * @param  string  $paymentInformation  Payment reason (max 140 characters).
     * @param  string  $debtorName  Name of the payer, i.e. the account holder (max 70 characters).
     * @param  string|null  $debtorIban  IBAN of the payer; null for the IBAN of the client configuration.
     * @param  DateTimeInterface|null  $requestedExecutionDate  Execution date (its calendar date is used); null leaves it to the bank.
     * @param  string|null  $endToEndId  Identifier chosen by the payer (max 35 characters); "" and "NOTPROVIDED" mean none.
     * @param  string  $categoryPurpose  ISO 20022 category purpose code, four uppercase letters (default CASH, as in 2.x).
     * @param  string  $currency  ISO 4217 currency code (default EUR).
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
        ?DateTimeInterface $requestedExecutionDate = null,
        ?string $endToEndId = null,
        string $categoryPurpose = self::DEFAULT_CATEGORY_PURPOSE,
        string $currency = self::DEFAULT_CURRENCY,
    ) {
        $this->creditorName = Validator::text($creditorName, 'creditor name', Validator::MAX_NAME_LENGTH);
        $this->creditorIban = Validator::iban($creditorIban, 'creditor IBAN');
        $this->amount = Validator::amount($amount);
        $this->paymentInformation = Validator::text($paymentInformation, 'payment information', Validator::MAX_PAYMENT_INFORMATION_LENGTH);
        $this->debtorName = Validator::text($debtorName, 'debtor name', Validator::MAX_NAME_LENGTH);
        $this->debtorIban = $debtorIban === null ? null : Validator::iban($debtorIban, 'debtor IBAN');
        $this->requestedExecutionDate = $requestedExecutionDate === null ? null : DateTimeImmutable::createFromInterface($requestedExecutionDate);
        $this->endToEndId = Validator::endToEndId($endToEndId);
        $this->categoryPurpose = Validator::code($categoryPurpose, 'category purpose', 4);
        $this->currency = Validator::code($currency, 'currency', 3);
    }
}
