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
use Shellrent\OpenBanking\Request\InstantPayment;
use Shellrent\OpenBanking\Request\Validator;
use Shellrent\OpenBanking\Response\SctInstant\InstantExecution;
use Shellrent\OpenBanking\Response\SctInstant\InstantPaymentDetails;
use Shellrent\OpenBanking\Response\SctInstant\InstantPaymentList;
use Shellrent\OpenBanking\Response\SctInstant\InstantSimulation;
use Shellrent\OpenBanking\Support\DateFormat;
use Shellrent\OpenBanking\Support\DateParser;
use Shellrent\OpenBanking\Support\Url;

/**
 * SCT Instant payments (bonifico istantaneo) from the configured account.
 *
 * Flow: simulate() → check the VoP outcome → execute() within 5 minutes → if pending, resubmit();
 * see docs/payment-flows.md. Obtain it with IntesaSanPaoloClient::sctInstant().
 */
final class SctInstantApi
{
    /**
     * Page size of the payment list (the API default is 50).
     */
    public const int LIST_PAGE_SIZE = 100;

    /**
     * @internal
     */
    public function __construct(
        private readonly ClientConfig $config,
        private readonly Transport $transport,
        private readonly Paginator $paginator,
    ) {}

    /**
     * Checks the feasibility of the payment, including the Verification of Payee.
     *
     * Nothing is paid. The result holds the simulation ID, valid for 5 minutes, and the VoP outcome.
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedResponseException
     */
    public function simulate(InstantPayment $payment): InstantSimulation
    {
        return InstantSimulation::fromResponse($this->transport->post('payments/sct/instant/simulation', $this->body($payment)));
    }

    /**
     * Executes a simulated payment. **This moves money.**
     *
     * The simulation must be accepted and must belong to this payment (same creditor IBAN and amount).
     * Executing counts as accepting the Verification of Payee outcome: unless it is a full match,
     * the payment is executed only with `$acceptVopResult = true`.
     *
     * After a TransportException the outcome is unknown: check the status or the payment list
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
    public function execute(InstantPayment $payment, InstantSimulation $simulation, bool $acceptVopResult = false): InstantExecution
    {
        $simulationId = PaymentGuard::simulationId($simulation, $payment->creditorIban, $payment->amount, $acceptVopResult);

        return InstantExecution::fromResponse($this->transport->post(
            'payments/sct/instant',
            [...$this->body($payment), 'simulationId' => $simulationId],
        ));
    }

    /**
     * Resubmits a payment whose execution stayed pending (PNDG), as allowed by InstantExecution::canBeResubmitted().
     *
     * Pass the same payment and simulation ID of the original execution, and the resubmit ID it returned.
     *
     * @throws InvalidRequestException When an identifier is empty.
     * @throws ApiException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedResponseException
     */
    public function resubmit(InstantPayment $payment, string $simulationId, string $resubmitId): InstantExecution
    {
        $simulationId = Validator::text($simulationId, 'simulation ID', PHP_INT_MAX);
        $resubmitId = Validator::text($resubmitId, 'resubmit ID', PHP_INT_MAX);

        return InstantExecution::fromResponse($this->transport->post('payments/sct/instant', [
            ...$this->body($payment),
            'simulationId' => $simulationId,
            'resubmit' => true,
            'resubmitId' => $resubmitId,
        ]));
    }

    /**
     * Status of a payment, by the order ID returned by the execution.
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedResponseException
     */
    public function status(string $orderId, ?string $paymentId = null): InstantPaymentDetails
    {
        $orderId = Validator::text($orderId, 'order ID', PHP_INT_MAX);
        $query = $paymentId === null || $paymentId === '' ? [] : ['paymentId' => $paymentId];

        return InstantPaymentDetails::fromResponse($this->transport->get(
            sprintf('payments/sct/instant/%s/history/%s', rawurlencode($this->config->iban), rawurlencode($orderId)),
            $query,
        ));
    }

    /**
     * Outgoing SCT Instant payments of a date range (up to 18 months back), all pages included.
     *
     * @param  DateTimeInterface|null  $from  First day; null for one month ago (as in 2.x).
     * @param  DateTimeInterface|null  $to  Last day; null for today.
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedResponseException
     */
    public function payments(?DateTimeInterface $from = null, ?DateTimeInterface $to = null): InstantPaymentList
    {
        $query = [
            'fromDate' => DateParser::format($from ?? new DateTimeImmutable('-1 month', DateParser::timezone()), DateFormat::Compact),
            'offset' => 0,
            'limit' => self::LIST_PAGE_SIZE,
            'paymentDirection' => 'O',
        ];

        if ($to !== null) {
            $query['toDate'] = DateParser::format($to, DateFormat::Compact);
        }

        $pages = [];
        $nextLink = static function (ApiResponse $page): ?string {
            $payload = $page->requirePayload();

            // Like 2.x, stop on an empty page even if it links to another one.
            if ($payload->list('payments') === []) {
                return null;
            }

            // "paymentDirection" is required: keep it on every page (docs/api-quirks.md, Q-42).
            return Url::withQueryValue($payload->object('links')?->string('next'), 'paymentDirection', 'O');
        };

        foreach ($this->paginator->pages(sprintf('payments/sct/instant/%s/history', rawurlencode($this->config->iban)), $query, $nextLink) as $page) {
            $pages[] = $page->requirePayload();
        }

        if ($pages === []) {
            throw new UnexpectedResponseException('The payment list request returned no pages.');
        }

        return InstantPaymentList::fromPages($pages);
    }

    /**
     * Body shared by simulation and execution.
     *
     * Empty "endToEndId" and "siaCode" are sent as "" in the sandbox and omitted in Live (docs/api-quirks.md, Q-34).
     *
     * @return array<string, scalar>
     */
    private function body(InstantPayment $payment): array
    {
        $body = [
            'debtorName' => $payment->debtorName,
            'debtorIBAN' => $payment->debtorIban ?? $this->config->iban,
            'creditorName' => $payment->creditorName,
            'creditorIBAN' => $payment->creditorIban,
            'amount' => $payment->amount,
            'paymentInformation' => $payment->paymentInformation,
        ];

        foreach (['endToEndId' => $payment->endToEndId, 'siaCode' => $payment->siaCode] as $field => $value) {
            if ($value !== null) {
                $body[$field] = $value;
            } elseif ($this->config->environment === Environment::Sandbox) {
                $body[$field] = '';
            }
        }

        return $body;
    }
}
