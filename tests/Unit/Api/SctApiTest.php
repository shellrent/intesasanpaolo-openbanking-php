<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Api;

use DateTimeImmutable;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Api\PaymentGuard;
use Shellrent\OpenBanking\Api\SctApi;
use Shellrent\OpenBanking\Config\Environment;
use Shellrent\OpenBanking\Enum\PaymentStatus;
use Shellrent\OpenBanking\Enum\ValidationStatus;
use Shellrent\OpenBanking\Enum\VopMatchResult;
use Shellrent\OpenBanking\Exception\InvalidRequestException;
use Shellrent\OpenBanking\Exception\VopConfirmationRequiredException;
use Shellrent\OpenBanking\IntesaSanPaoloClient;
use Shellrent\OpenBanking\Request\SctPayment;
use Shellrent\OpenBanking\Response\Sct\SctDates;
use Shellrent\OpenBanking\Response\Sct\SctExecution;
use Shellrent\OpenBanking\Response\Sct\SctPaymentDetails;
use Shellrent\OpenBanking\Response\Sct\SctPaymentList;
use Shellrent\OpenBanking\Response\Sct\SctPaymentListItem;
use Shellrent\OpenBanking\Response\Sct\SctRevocation;
use Shellrent\OpenBanking\Response\Sct\SctSimulation;
use Shellrent\OpenBanking\Tests\Support\MockHttp;
use Shellrent\OpenBanking\Tests\Unit\TestCase;

#[CoversClass(SctApi::class)]
#[CoversClass(PaymentGuard::class)]
#[CoversClass(SctSimulation::class)]
#[CoversClass(SctExecution::class)]
#[CoversClass(SctPaymentDetails::class)]
#[CoversClass(SctPaymentList::class)]
#[CoversClass(SctPaymentListItem::class)]
#[CoversClass(SctRevocation::class)]
#[CoversClass(SctDates::class)]
final class SctApiTest extends TestCase
{
    private const string BASE = 'https://external-api.intesasanpaolo.com/twa/live/v1/payments/bonsct';

    private const string CREDITOR_IBAN = 'IT00X0000000000000000000002';

    #[Test]
    public function it_simulates_a_payment(): void
    {
        $http = new MockHttp(MockHttp::token(), $this->simulationResponse('MTCH', ['X-ISP-Transaction-ID' => 'TX-S']));

        $simulation = $this->api($http)->simulate($this->payment(requestedExecutionDate: new DateTimeImmutable('2026-01-02')));

        self::assertSame(self::BASE.'/simulation', (string) $http->request(1)->getUri());
        self::assertSame([
            'debtorName' => 'Example Srl',
            'debtorIban' => self::IBAN,
            'creditorName' => 'Fornitore Esempio Srl',
            'creditorIban' => self::CREDITOR_IBAN,
            'amount' => 1250,
            'currency' => 'EUR',
            'paymentInformation' => 'Fattura 2/2026',
            'requestedExecutionDate' => '02/01/2026',
            'categoryPurpose' => 'SUPP',
        ], $http->jsonBody(1), 'Q-12: SCT uses "debtorIban"; Q-34: in Live an empty endToEndId is omitted');

        self::assertTrue($simulation->isAccepted());
        self::assertSame(ValidationStatus::Accepted, $simulation->validationStatus);
        self::assertSame('SIM-SCT-0001', $simulation->simulationId);
        self::assertNull($simulation->endToEndId);
        self::assertSame(1250.0, $simulation->amount);
        self::assertSame(0.9, $simulation->fees, 'Q-15: "fees" in the current spec');
        self::assertSame('INTESA SANPAOLO SPA', $simulation->creditorAgent);
        self::assertSame('2026-01-02', $simulation->executionDate?->format('Y-m-d'), 'Q-09: "02/01/2026" is January 2nd');
        self::assertSame('2026-01-02', $simulation->valueDate?->format('Y-m-d'));
        self::assertSame('2026-01-05', $simulation->settlementDate?->format('Y-m-d'));
        self::assertSame(self::CREDITOR_IBAN, $simulation->creditor->iban);
        self::assertSame(VopMatchResult::Match, $simulation->vop->result);
        self::assertSame('TX-S', $simulation->transactionId);
    }

    #[Test]
    public function it_leaves_the_execution_date_to_the_bank_when_not_given(): void
    {
        $http = new MockHttp(MockHttp::token(), $this->simulationResponse('MTCH'));

        $this->api($http)->simulate($this->payment());

        self::assertArrayNotHasKey('requestedExecutionDate', $http->jsonBody(1));
    }

    /**
     * Q-34: in the sandbox an empty endToEndId must be present as "".
     */
    #[Test]
    public function it_sends_an_empty_end_to_end_id_in_the_sandbox(): void
    {
        $http = new MockHttp(MockHttp::token(), $this->simulationResponse('MTCH'));

        $this->api($http, Environment::Sandbox)->simulate($this->payment());

        self::assertSame('', $http->jsonBody(1)['endToEndId'] ?? null);
    }

    #[Test]
    public function it_executes_a_simulation_with_a_full_vop_match(): void
    {
        $http = new MockHttp(MockHttp::token(), $this->simulationResponse('MTCH'), MockHttp::fixture('Sct/execution', ['X-ISP-Transaction-ID' => 'TX-E']));
        $api = $this->api($http);

        $execution = $api->execute($this->payment(), $api->simulate($this->payment()));

        self::assertSame(self::BASE, (string) $http->request(2)->getUri());
        $body = $http->jsonBody(2);
        self::assertSame('simulationId', array_key_first($body), 'simulationId comes first, as in 2.x');
        self::assertSame('SIM-SCT-0001', $body['simulationId'] ?? null);

        self::assertSame('CRO-SCT-0001', $execution->customerCro);
        self::assertSame('PAY-SCT-0001', $execution->paymentId);
        self::assertSame(PaymentStatus::Accepted, $execution->paymentStatus, 'Q-28: ACTC, accepted but funds not yet blocked');
        self::assertTrue($execution->paymentStatus->isAccepted(), 'M-03: an ACTC order must not be paid again');
        self::assertFalse($execution->paymentStatus->isFinal());
        self::assertSame(0.9, $execution->fees, 'Q-15: "commissioni" in older responses');
        self::assertSame('BCITITMM', $execution->creditor->bic);
        self::assertSame('2026-01-02', $execution->executionDate?->format('Y-m-d'));
        self::assertSame('2026-01-05', $execution->settlementDate?->format('Y-m-d'));
        self::assertSame('2026-01-02 17:30:00', $execution->revokeDate?->format('Y-m-d H:i:s'));
        self::assertSame('17:30:00', $execution->revokeTime);
        self::assertSame('TX-E', $execution->transactionId);
    }

    /**
     * Q-37: the VoP safeguard applies to SCT as well.
     */
    #[Test]
    public function it_refuses_to_execute_without_a_full_vop_match(): void
    {
        $http = new MockHttp(MockHttp::token(), $this->simulationResponse('NO MATCH'), MockHttp::fixture('Sct/execution'));
        $api = $this->api($http);
        $simulation = $api->simulate($this->payment());

        try {
            $api->execute($this->payment(), $simulation);
            self::fail('VopConfirmationRequiredException was not thrown');
        } catch (VopConfirmationRequiredException $exception) {
            self::assertSame(VopMatchResult::NoMatch, $exception->vop->result);
        }

        self::assertSame(2, $http->count());
        self::assertSame('CRO-SCT-0001', $api->execute($this->payment(), $simulation, acceptVopResult: true)->customerCro);
    }

    #[Test]
    public function it_refuses_a_simulation_of_another_payment(): void
    {
        $http = new MockHttp(MockHttp::token(), $this->simulationResponse('MTCH'));
        $api = $this->api($http);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('different amount');

        $api->execute($this->payment(amount: 1250.01), $api->simulate($this->payment()));
    }

    #[Test]
    public function it_executes_a_simulation_restored_from_storage(): void
    {
        $http = new MockHttp(MockHttp::token(), $this->simulationResponse('MTCH'), MockHttp::fixture('Sct/execution'));
        $api = $this->api($http);

        $data = json_decode(json_encode($api->simulate($this->payment())->toArray(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        $restored = SctSimulation::fromArray($data);

        self::assertSame('2026-01-02', $restored->executionDate?->format('Y-m-d'));
        self::assertSame(0.9, $restored->fees);
        self::assertSame(VopMatchResult::Match, $restored->vop->result);
        self::assertSame('PAY-SCT-0001', $api->execute($this->payment(), $restored)->paymentId);
    }

    /**
     * Q-04 and Q-29: the status GET carries "Content-Type: application/json" and accepts every documented identifier.
     */
    #[Test]
    public function it_reads_the_status_of_a_payment_by_any_identifier(): void
    {
        $http = new MockHttp(
            MockHttp::token(),
            MockHttp::fixture('Sct/status'),
            MockHttp::fixture('Sct/status'),
            MockHttp::fixture('Sct/status'),
        );
        $api = $this->api($http);

        $status = $api->status(customerCro: 'CRO-SCT-0001');
        $api->status(simulationId: 'SIM-SCT-0001');
        $api->status(paymentId: 'PAY-SCT-0001', endToEndId: 'E2E-SCT-0001');

        self::assertSame(self::BASE.'/'.self::IBAN.'/history?customerCRO=CRO-SCT-0001', (string) $http->request(1)->getUri());
        self::assertSame(self::BASE.'/'.self::IBAN.'/history?simulationId=SIM-SCT-0001', (string) $http->request(2)->getUri());
        self::assertSame(self::BASE.'/'.self::IBAN.'/history?paymentId=PAY-SCT-0001&endToEndId=E2E-SCT-0001', (string) $http->request(3)->getUri());
        self::assertSame('application/json', $http->request(1)->getHeaderLine('Content-Type'));

        self::assertSame(PaymentStatus::InProgress, $status->paymentStatus);
        self::assertSame('E2E-SCT-0001', $status->endToEndId);
        self::assertSame('2026-01-05', $status->settlementDate?->format('Y-m-d'));
        self::assertSame('IT', $status->creditor->nation);
        self::assertSame('Via Esempio 2, Milano', $status->creditor->address);
    }

    #[Test]
    public function it_requires_an_identifier_for_the_status(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('At least one of customerCRO, paymentId, endToEndId, simulationId must be given.');

        $this->api(new MockHttp)->status(customerCro: ' ');
    }

    /**
     * Q-04 and Q-43: same list parameters and headers as 2.x on every page.
     */
    #[Test]
    public function it_lists_the_payments_of_a_date_range(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::fixture('Sct/list-page-1'), MockHttp::fixture('Sct/list-page-2'));

        $list = $this->api($http)->payments(new DateTimeImmutable('2025-12-15'), new DateTimeImmutable('2026-01-15'));

        self::assertSame(
            self::BASE.'/'.self::IBAN.'/list?fromDate=20251215&offset=0&limit=30&paymentDirection=O&toDate=20260115',
            (string) $http->request(1)->getUri(),
        );
        self::assertSame(self::BASE.'/'.self::IBAN.'/list?offset=PAY-SCT-0001&paymentDirection=O', (string) $http->request(2)->getUri());
        self::assertSame('application/json', $http->request(1)->getHeaderLine('Content-Type'));
        self::assertSame('application/json', $http->request(2)->getHeaderLine('Content-Type'));

        self::assertSame(2, $list->totalCount);
        self::assertTrue($list->isComplete());
        self::assertSame('2026-01-15T10:05:00+00:00', $list->executionDate?->format(DATE_ATOM), 'Q-17: "executionInquiry"');

        [$first, $second] = $list->payments;
        self::assertSame('CRO-SCT-0001', $first->customerCro);
        self::assertSame('E2E-SCT-0001', $first->endToEndId);
        self::assertSame('SIM-SCT-0001', $first->simulationId);
        self::assertSame('SUPP', $first->categoryPurpose);
        self::assertSame(PaymentStatus::Executed, $first->paymentStatus);
        self::assertSame('2026-01-02', $first->executionDate?->format('Y-m-d'));
        self::assertSame(PaymentStatus::Canceled, $second->paymentStatus);
        self::assertNull($second->endToEndId);
        self::assertSame(80.5, $second->amount);
    }

    /**
     * Q-43: the spec declares "links" as a string, while the examples and 2.x use {"next": …}.
     */
    #[Test]
    public function it_follows_a_next_link_sent_as_a_plain_url(): void
    {
        $http = new MockHttp(
            MockHttp::token(),
            MockHttp::envelope([
                'paymentsTotalCount' => 2,
                'links' => self::BASE.'/'.self::IBAN.'/list?offset=PAY-1',
                'paymentsResults' => [['paymentId' => 'PAY-1', 'amount' => 10]],
            ]),
            MockHttp::envelope(['paymentsTotalCount' => 2, 'paymentsResults' => [['paymentId' => 'PAY-2', 'amount' => 20]]]),
        );

        $list = $this->api($http)->payments(new DateTimeImmutable('2026-01-01'));

        self::assertSame(self::BASE.'/'.self::IBAN.'/list?offset=PAY-1&paymentDirection=O', (string) $http->request(2)->getUri());
        self::assertTrue($list->isComplete());
    }

    /**
     * D-58: an "OK" inside an unsuccessful response is contradictory and never counts as revoked.
     */
    #[Test]
    public function it_never_reports_a_revocation_inside_an_unsuccessful_response(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::json(['success' => false, 'payload' => ['status' => 'OK']]));

        $revocation = $this->api($http)->revoke(paymentId: 'PAY-SCT-0004');

        self::assertNull($revocation->revoked);
        self::assertFalse($revocation->isRevoked());
    }

    #[Test]
    public function it_stops_listing_on_an_empty_page(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::envelope([
            'paymentsTotalCount' => 0,
            'links' => ['next' => self::BASE.'/'.self::IBAN.'/list?offset=X'],
            'paymentsResults' => [],
        ]));

        self::assertSame([], $this->api($http)->payments(new DateTimeImmutable('2026-01-01'))->payments);
        self::assertSame(2, $http->count());
    }

    /**
     * Q-31, Q-40.
     */
    #[Test]
    public function it_revokes_a_payment(): void
    {
        $http = new MockHttp(
            MockHttp::token(),
            MockHttp::fixture('Sct/revoke'),
            MockHttp::envelope(['paymentId' => 'PAY-SCT-0002', 'customerCRO' => 'CRO-SCT-0002', 'status' => 'KO']),
        );
        $api = $this->api($http);

        $revoked = $api->revoke('CRO-SCT-0001', 'PAY-SCT-0001');
        $refused = $api->revoke(paymentId: 'PAY-SCT-0002');

        self::assertSame(self::BASE.'/'.self::IBAN.'/revoke?customerCRO=CRO-SCT-0001&paymentId=PAY-SCT-0001', (string) $http->request(1)->getUri());
        self::assertSame('', $http->request(1)->getHeaderLine('Content-Type'), '2.x did not send a content type on revoke');
        self::assertTrue($revoked->isRevoked());
        self::assertSame('CRO-SCT-0001', $revoked->customerCro);
        self::assertFalse($refused->isRevoked());
        self::assertFalse($refused->revoked);
        self::assertSame('KO', $refused->rawStatus);
    }

    /**
     * Q-31: the sandbox refuses a revocation with "success: false", no errors and a status with a description.
     */
    #[Test]
    public function it_reports_a_refused_revocation_sent_as_unsuccessful(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::json([
            'success' => false,
            'payload' => ['paymentId' => 'PAY-SCT-0003', 'status' => 'KO - Generic error'],
        ]));

        $revocation = $this->api($http)->revoke(paymentId: 'PAY-SCT-0003');

        self::assertFalse($revocation->revoked);
        self::assertFalse($revocation->isRevoked());
        self::assertSame('KO - Generic error', $revocation->rawStatus);
        self::assertSame('PAY-SCT-0003', $revocation->paymentId);
    }

    #[Test]
    public function it_requires_an_identifier_to_revoke(): void
    {
        $this->expectException(InvalidRequestException::class);

        $this->api(new MockHttp)->revoke();
    }

    private function payment(float $amount = 1250, ?DateTimeImmutable $requestedExecutionDate = null): SctPayment
    {
        return new SctPayment(
            creditorName: 'Fornitore Esempio Srl',
            creditorIban: self::CREDITOR_IBAN,
            amount: $amount,
            paymentInformation: 'Fattura 2/2026',
            debtorName: 'Example Srl',
            requestedExecutionDate: $requestedExecutionDate,
            categoryPurpose: 'SUPP',
        );
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function simulationResponse(string $vopMatchResult, array $headers = []): Response
    {
        return MockHttp::fixture('Sct/simulation', ['vopMatchResult' => $vopMatchResult, ...$headers]);
    }

    private function api(MockHttp $http, Environment $environment = Environment::Live): SctApi
    {
        return (new IntesaSanPaoloClient($this->config($environment), $http->client))->sct();
    }
}
