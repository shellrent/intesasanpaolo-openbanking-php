<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Api;

use Shellrent\OpenBanking\Exception\ApiException;
use Shellrent\OpenBanking\Exception\AuthenticationException;
use Shellrent\OpenBanking\Exception\InvalidRequestException;
use Shellrent\OpenBanking\Exception\TransportException;
use Shellrent\OpenBanking\Exception\UnexpectedResponseException;
use Shellrent\OpenBanking\Http\Transport;
use Shellrent\OpenBanking\Request\Validator;
use Shellrent\OpenBanking\Response\Iban\IbanFormalCheck;
use Shellrent\OpenBanking\Response\Iban\IbanVerification;

/**
 * IBAN checks. They are not tied to the configured account. Obtain them with IntesaSanPaoloClient::ibanTools().
 *
 * For the SCT Instant reachability of an IBAN see SctInstantApi::reachability().
 */
final class IbanToolsApi
{
    /**
     * @internal
     */
    public function __construct(private readonly Transport $transport) {}

    /**
     * Formal (syntactic) check of an Intesa Sanpaolo IBAN, split into its parts.
     *
     * In Live it answers only for Intesa Sanpaolo IBANs (ABI 03069), and does not check that the account
     * exists. Any other IBAN gives an ApiException: "WI01" for another bank or a foreign IBAN, HTTP 500 for
     * wrong check digits (docs/api-quirks.md, Q-52). The IBAN is sent as given (spaces removed, uppercase).
     *
     * @throws InvalidRequestException When the IBAN is empty.
     * @throws ApiException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedResponseException
     */
    public function formalCheck(string $iban): IbanFormalCheck
    {
        $iban = strtoupper((string) preg_replace('/\s+/', '', $iban));

        if ($iban === '') {
            throw new InvalidRequestException('The IBAN must not be empty.');
        }

        return IbanFormalCheck::fromResponse($this->transport->get('tools/iban/formal-check', ['iban' => $iban]));
    }

    /**
     * Checks that an Italian IBAN exists and belongs to the holder with this fiscal code or VAT number.
     *
     * At least one of the two is required: the API refuses the IBAN alone (docs/decisions.md, D-64).
     * Works for Intesa Sanpaolo accounts and for the banks joining the CBI Globe "Check IBAN" service.
     * A negative outcome (unknown account, other holder) is a result with isAllowed() false; a foreign IBAN
     * or a bank outside the service gives an ApiException (docs/api-quirks.md, Q-20).
     *
     * @param  string|null  $fiscalCode  Codice fiscale of the expected holder.
     * @param  string|null  $vatNumber  Partita IVA of the expected holder.
     *
     * @throws InvalidRequestException When both the fiscal code and the VAT number are missing, or an input is malformed.
     * @throws ApiException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedResponseException
     */
    public function verify(string $iban, ?string $fiscalCode = null, ?string $vatNumber = null): IbanVerification
    {
        $query = ['iban' => Validator::iban($iban, 'IBAN')];

        $fiscalCode = Validator::taxCode($fiscalCode, 'fiscal code');
        $vatNumber = Validator::taxCode($vatNumber, 'VAT number');

        if ($fiscalCode === null && $vatNumber === null) {
            throw new InvalidRequestException('A fiscal code or a VAT number of the holder is required to verify an IBAN.');
        }

        if ($fiscalCode !== null) {
            $query['fiscalCode'] = $fiscalCode;
        }

        if ($vatNumber !== null) {
            $query['vatNumber'] = $vatNumber;
        }

        // A negative outcome may arrive with "success: false" and no errors (docs/api-quirks.md, Q-20).
        return IbanVerification::fromResponse(
            $this->transport->get('tools/iban/chargeabilityMulti', $query, acceptNegativeOutcome: true),
        );
    }
}
