<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Api;

use DateTimeImmutable;
use DateTimeInterface;
use Shellrent\OpenBanking\Config\ClientConfig;
use Shellrent\OpenBanking\Config\Environment;
use Shellrent\OpenBanking\Exception\ApiException;
use Shellrent\OpenBanking\Exception\AuthenticationException;
use Shellrent\OpenBanking\Exception\InvalidRequestException;
use Shellrent\OpenBanking\Exception\TransportException;
use Shellrent\OpenBanking\Exception\UnexpectedResponseException;
use Shellrent\OpenBanking\Exception\VopConfirmationRequiredException;
use Shellrent\OpenBanking\Http\ApiResponse;
use Shellrent\OpenBanking\Http\Paginator;
use Shellrent\OpenBanking\Http\Transport;
use Shellrent\OpenBanking\Request\SctPayment;
use Shellrent\OpenBanking\Request\Validator;
use Shellrent\OpenBanking\Response\Sct\SctExecution;
use Shellrent\OpenBanking\Response\Sct\SctPaymentDetails;
use Shellrent\OpenBanking\Response\Sct\SctPaymentList;
use Shellrent\OpenBanking\Response\Sct\SctRevocation;
use Shellrent\OpenBanking\Response\Sct\SctSimulation;
use Shellrent\OpenBanking\Support\DateFormat;
use Shellrent\OpenBanking\Support\DateParser;
use Shellrent\OpenBanking\Support\Url;

/**
 * SCT payments (bonifico ordinario) from the configured account.
 *
 * Flow: simulate() → check the VoP outcome → execute() within 5 minutes → status() / revoke();
 * see docs/payment-flows.md. Obtain it with IntesaSanPaoloClient::sct().
 */
final class SctApi
{
    /**
     * Page size of the payment list, as sent by the 2.x SDK (docs/api-quirks.md, Q-43).
     */
    public const int LIST_PAGE_SIZE = 30;

    /**
     * The status and list GET requests carry a JSON content type, as in 2.x (docs/api-quirks.md, Q-04).
     */
    private const array JSON_CONTENT_TYPE = ['Content-Type' => 'application/json'];

    /**
     * @internal
     */
    public function __construct(
        private readonly ClientConfig $config,
        private readonly Transport $transport,
        private readonly Paginator $paginator,
    ) {}

    /**
     * Checks the feasibility of the payment, including the Verification of Payee, and returns the planned
     * dates and fees. Nothing is paid; the simulation ID is valid for 5 minutes.
     *
     * @throws InvalidRequestException When the end-to-end ID is longer than Live accepts (35 characters).
     * @throws ApiException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedResponseException
     */
    public function simulate(SctPayment $payment): SctSimulation
    {
        return SctSimulation::fromResponse($this->transport->post('payments/bonsct/simulation', $this->body($payment)));
    }

    /**
     * Executes a simulated payment. **This moves money.**
     *
     * The simulation must be accepted and must belong to this payment (same creditor IBAN and amount).
     * Executing counts as accepting the Verification of Payee outcome: unless it is a full match,
     * the payment is executed only with `$acceptVopResult = true`.
     *
     * After a TransportException, an UnexpectedResponseException or an ApiException with a 5xx status
     * the outcome is unknown: look for the payment with status() (e.g. by simulation ID) or payments()
     * before trying again.
     *
     * @param  bool  $acceptVopResult  Execute even if the VoP outcome is not a full match, after reviewing it.
     *
     * @throws InvalidRequestException When the simulation was rejected or belongs to another payment.
     * @throws VopConfirmationRequiredException When the VoP outcome is not a full match and was not accepted.
     * @throws ApiException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedResponseException
     */
    public function execute(SctPayment $payment, SctSimulation $simulation, bool $acceptVopResult = false): SctExecution
    {
        $simulationId = PaymentGuard::simulationId($simulation, $payment->creditorIban, $payment->creditorName, $payment->amount, $acceptVopResult);

        return SctExecution::fromResponse($this->transport->post(
            'payments/bonsct',
            ['simulationId' => $simulationId, ...$this->body($payment)],
        ));
    }

    /**
     * Status of a payment, looked up by one of its identifiers (docs/api-quirks.md, Q-29).
     *
     * The simulation ID and the end-to-end ID are useful after an execution with an unknown outcome.
     *
     * @throws InvalidRequestException When no identifier is given.
     * @throws ApiException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedResponseException
     */
    public function status(
        ?string $customerCro = null,
        ?string $paymentId = null,
        ?string $endToEndId = null,
        ?string $simulationId = null,
    ): SctPaymentDetails {
        $query = self::identifiers([
            'customerCRO' => $customerCro,
            'paymentId' => $paymentId,
            'endToEndId' => $endToEndId,
            'simulationId' => $simulationId,
        ]);

        return SctPaymentDetails::fromResponse($this->transport->get($this->path('history'), $query, self::JSON_CONTENT_TYPE));
    }

    /**
     * Outgoing SCT payments of a date range (up to 18 months back), all pages included.
     *
     * @param  DateTimeInterface|null  $from  First day; null for one month ago (as in 2.x).
     * @param  DateTimeInterface|null  $to  Last day; null for today.
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedResponseException
     */
    public function payments(?DateTimeInterface $from = null, ?DateTimeInterface $to = null): SctPaymentList
    {
        // Same parameters as 2.x, which work in production although "limit" and "paymentDirection"
        // are not in the spec (docs/api-quirks.md, Q-43).
        $query = [
            'fromDate' => DateParser::format($from ?? new DateTimeImmutable('-1 month', DateParser::timezone()), DateFormat::Compact),
            'offset' => 0,
            'limit' => self::LIST_PAGE_SIZE,
            'paymentDirection' => 'O',
        ];

        if ($to !== null) {
            $query['toDate'] = DateParser::format($to, DateFormat::Compact);
        }

        $nextLink = static function (ApiResponse $page): ?string {
            $payload = $page->requirePayload();

            // Like 2.x, stop on an empty page even if it links to another one.
            if ($payload->list('paymentsResults') === []) {
                return null;
            }

            return Url::withQueryValue($payload->link('links'), 'paymentDirection', 'O');
        };

        $pages = [];

        foreach ($this->paginator->pages($this->path('list'), $query, $nextLink, self::JSON_CONTENT_TYPE) as $page) {
            $pages[] = $page->requirePayload();
        }

        if ($pages === []) {
            throw new UnexpectedResponseException('The payment list request returned no pages.');
        }

        return SctPaymentList::fromPages($pages);
    }

    /**
     * Asks to revoke a payment, identified by its CRO or payment ID. **The bank accepts it only before
     * its cut-off** (docs/api-quirks.md, Q-40): check SctRevocation::isRevoked().
     *
     * @throws InvalidRequestException When no identifier is given.
     * @throws ApiException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedResponseException
     */
    public function revoke(?string $customerCro = null, ?string $paymentId = null): SctRevocation
    {
        $query = self::identifiers(['customerCRO' => $customerCro, 'paymentId' => $paymentId]);

        // A refused revocation may arrive with "success: false" and no errors (docs/api-quirks.md, Q-31).
        return SctRevocation::fromResponse($this->transport->get($this->path('revoke'), $query, acceptNegativeOutcome: true));
    }

    /**
     * Body shared by simulation and execution.
     *
     * An empty "endToEndId" is sent as "" in the sandbox and omitted in Live (docs/api-quirks.md, Q-34).
     *
     * @return array<string, scalar>
     */
    private function body(SctPayment $payment): array
    {
        if ($this->config->environment === Environment::Live) {
            Validator::liveEndToEndId($payment->endToEndId);
        }

        $body = [
            'debtorName' => $payment->debtorName,
            'debtorIban' => $payment->debtorIban ?? $this->config->iban,
            'creditorName' => $payment->creditorName,
            'creditorIban' => $payment->creditorIban,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'paymentInformation' => $payment->paymentInformation,
        ];

        if ($payment->requestedExecutionDate !== null) {
            $body['requestedExecutionDate'] = DateParser::format($payment->requestedExecutionDate, DateFormat::Italian);
        }

        if ($payment->endToEndId !== null) {
            $body['endToEndId'] = $payment->endToEndId;
        } elseif ($this->config->environment === Environment::Sandbox) {
            $body['endToEndId'] = '';
        }

        $body['categoryPurpose'] = $payment->categoryPurpose;

        return $body;
    }

    /**
     * @param  array<string, string|null>  $identifiers
     * @return array<string, string>
     *
     * @throws InvalidRequestException When all the identifiers are empty.
     */
    private static function identifiers(array $identifiers): array
    {
        $query = [];

        foreach ($identifiers as $name => $value) {
            $value = Validator::optional($value);

            if ($value !== null) {
                $query[$name] = $value;
            }
        }

        if ($query === []) {
            throw new InvalidRequestException(sprintf('At least one of %s must be given.', implode(', ', array_keys($identifiers))));
        }

        return $query;
    }

    private function path(string $resource): string
    {
        return sprintf('payments/bonsct/%s/%s', rawurlencode($this->config->iban), $resource);
    }
}
