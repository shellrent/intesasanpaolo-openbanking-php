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
     * Formal (syntactic) check of an IBAN, of any bank: real time, OK/KO.
     *
     * The IBAN is sent as given (only spaces removed and letters uppercased): an invalid one gives
     * an IbanFormalCheck with isValid() false, not an exception.
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
     * Checks that an IBAN exists and, with a fiscal code or VAT number, that it belongs to that holder.
     *
     * Works for Intesa Sanpaolo accounts and for the banks joining the CBI Globe "Check IBAN" service.
     * With the IBAN alone it is the existence check ("Verifica esistenza IBAN", docs/v3/DECISIONS.md, D-51).
     *
     * @param  string|null  $fiscalCode  Codice fiscale of the expected holder.
     * @param  string|null  $vatNumber  Partita IVA of the expected holder.
     *
     * @throws InvalidRequestException When the IBAN, the fiscal code or the VAT number is malformed.
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

        if ($fiscalCode !== null) {
            $query['fiscalCode'] = $fiscalCode;
        }

        if ($vatNumber !== null) {
            $query['vatNumber'] = $vatNumber;
        }

        return IbanVerification::fromResponse($this->transport->get('tools/iban/chargeabilityMulti', $query));
    }
}
