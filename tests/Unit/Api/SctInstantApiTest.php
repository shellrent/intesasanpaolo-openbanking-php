<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Api;

use DateTimeImmutable;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Api\SctInstantApi;
use Shellrent\OpenBanking\Config\Environment;
use Shellrent\OpenBanking\Enum\InstantStatus;
use Shellrent\OpenBanking\Enum\PaymentStatus;
use Shellrent\OpenBanking\Enum\ValidationStatus;
use Shellrent\OpenBanking\Enum\VopMatchResult;
use Shellrent\OpenBanking\Exception\InvalidRequestException;
use Shellrent\OpenBanking\Exception\VopConfirmationRequiredException;
use Shellrent\OpenBanking\IntesaSanPaoloClient;
use Shellrent\OpenBanking\Request\InstantPayment;
use Shellrent\OpenBanking\Response\Party;
use Shellrent\OpenBanking\Response\SctInstant\InstantExecution;
use Shellrent\OpenBanking\Response\SctInstant\InstantPaymentDetails;
use Shellrent\OpenBanking\Response\SctInstant\InstantPaymentList;
use Shellrent\OpenBanking\Response\SctInstant\InstantPaymentListItem;
use Shellrent\OpenBanking\Response\SctInstant\InstantSimulation;
use Shellrent\OpenBanking\Tests\Support\MockHttp;
use Shellrent\OpenBanking\Tests\Unit\TestCase;

#[CoversClass(SctInstantApi::class)]
#[CoversClass(InstantSimulation::class)]
#[CoversClass(InstantExecution::class)]
#[CoversClass(InstantPaymentDetails::class)]
#[CoversClass(InstantPaymentList::class)]
#[CoversClass(InstantPaymentListItem::class)]
#[CoversClass(Party::class)]
final class SctInstantApiTest extends TestCase
{
    private const string BASE = 'https://external-api.intesasanpaolo.com/twa/live/v1';

    private const string CREDITOR_IBAN = 'IT00X0000000000000000000002';

    #[Test]
    public function it_simulates_a_payment(): void
    {
        $http = new MockHttp(MockHttp::token(), $this->simulationResponse('MTCH', ['X-ISP-Transaction-ID' => 'TX-S']));

        $simulation = $this->api($http)->simulate($this->payment());

        $request = $http->request(1);
        self::assertSame('POST', $request->getMethod());
        self::assertSame(self::BASE.'/payments/sct/instant/simulation', (string) $request->getUri());
        self::assertSame([
            'debtorName' => 'Example Srl',
            'debtorIBAN' => self::IBAN,
            'creditorName' => 'Mario Rossi',
            'creditorIBAN' => self::CREDITOR_IBAN,
            'amount' => 150.25,
            'paymentInformation' => 'Fattura 1/2026',
        ], $http->jsonBody(1), 'Q-34: in Live empty endToEndId and siaCode are omitted');

        self::assertTrue($simulation->isAccepted());
        self::assertSame(ValidationStatus::Accepted, $simulation->validationStatus, 'Q-25: "ACSC" means accepted');
        self::assertSame('ACSC', $simulation->rawValidationStatus);
        self::assertSame('SIM-0001', $simulation->simulationId);
        self::assertNull($simulation->endToEndId, 'Q-35: "NOTPROVIDED" means none');
        self::assertSame(150.25, $simulation->amount);
        self::assertSame('2026-01-15T10:00:00+00:00', $simulation->date?->format(DATE_ATOM));
        self::assertSame('Mario Rossi', $simulation->creditor->name);
        self::assertSame(self::CREDITOR_IBAN, $simulation->creditor->iban);
        self::assertSame('BCITITMM', $simulation->creditor->bic);
        self::assertSame('Example Srl', $simulation->debtor->ultimateName);
        self::assertSame(VopMatchResult::Match, $simulation->vop->result);
        self::assertTrue($simulation->vop->isMatch());
        self::assertSame('TX-S', $simulation->transactionId);
    }

    /**
     * Q-34: in the sandbox empty endToEndId and siaCode must be present as "".
     */
    #[Test]
    public function it_sends_empty_optional_ids_in_the_sandbox(): void
    {
        $http = new MockHttp(MockHttp::token(), $this->simulationResponse('MTCH'));

        $this->api($http, Environment::Sandbox)->simulate($this->payment());

        self::assertSame(['endToEndId' => '', 'siaCode' => ''], array_intersect_key($http->jsonBody(1), ['endToEndId' => 0, 'siaCode' => 0]));
    }

    #[Test]
    public function it_sends_the_optional_fields_when_given(): void
    {
        $http = new MockHttp(MockHttp::token(), $this->simulationResponse('MTCH'));

        $this->api($http)->simulate($this->payment(debtorIban: 'IT00X0000000000000000000009', endToEndId: 'E2E-0001', siaCode: 'AB123'));

        $body = $http->jsonBody(1);
        self::assertSame('IT00X0000000000000000000009', $body['debtorIBAN'] ?? null);
        self::assertSame('E2E-0001', $body['endToEndId'] ?? null);
        self::assertSame('AB123', $body['siaCode'] ?? null);
    }

    /**
     * F-36: an amount computed with floats is sent with two decimals.
     */
    #[Test]
    public function it_sends_amounts_without_float_noise(): void
    {
        $http = new MockHttp(MockHttp::token(), $this->simulationResponse('MTCH'));

        $this->api($http)->simulate($this->payment(amount: 0.1 + 0.2));

        self::assertStringContainsString('"amount":0.3,', (string) $http->request(1)->getBody());
    }

    #[Test]
    public function it_executes_a_simulation_with_a_full_vop_match(): void
    {
        $http = new MockHttp(MockHttp::token(), $this->simulationResponse('MTCH'), MockHttp::fixture('SctInstant/execution', ['X-ISP-Transaction-ID' => 'TX-E']));
        $api = $this->api($http);

        $execution = $api->execute($this->payment(), $api->simulate($this->payment()));

        self::assertSame(self::BASE.'/payments/sct/instant', (string) $http->request(2)->getUri());
        self::assertSame('SIM-0001', $http->jsonBody(2)['simulationId'] ?? null);
        self::assertArrayNotHasKey('resubmit', $http->jsonBody(2));

        self::assertSame('ORD-0001', $execution->orderId);
        self::assertSame('PAY-0001', $execution->paymentId);
        self::assertSame('CRO-0001', $execution->customerCro);
        self::assertSame('E2E-0001', $execution->endToEndId);
        self::assertNull($execution->resubmitId);
        self::assertSame(PaymentStatus::Executed, $execution->paymentStatus);
        self::assertTrue($execution->paymentStatus->isAccepted());
        self::assertFalse($execution->canBeResubmitted());
        self::assertSame(150.25, $execution->amount);
        self::assertSame(self::CREDITOR_IBAN, $execution->creditor->iban);
        self::assertSame('TX-E', $execution->transactionId);
    }

    /**
     * Q-37: executing counts as accepting the VoP outcome, so anything but a full match needs a confirmation.
     */
    #[Test]
    public function it_refuses_to_execute_without_a_full_vop_match(): void
    {
        $http = new MockHttp(MockHttp::token(), $this->simulationResponse('CMTC', ['vopMatchedName' => 'Mario Rossi Srl']));
        $api = $this->api($http);
        $simulation = $api->simulate($this->payment());

        try {
            $api->execute($this->payment(), $simulation);
            self::fail('VopConfirmationRequiredException was not thrown');
        } catch (VopConfirmationRequiredException $exception) {
            self::assertSame(VopMatchResult::CloseMatch, $exception->vop->result);
            self::assertSame('Mario Rossi Srl', $exception->vop->matchedName);
            self::assertStringContainsString('CMTC, suggested name: Mario Rossi Srl', $exception->getMessage());
        }

        self::assertSame(2, $http->count(), 'Nothing was sent to the execution API');
    }

    #[Test]
    public function it_refuses_to_execute_when_the_vop_outcome_is_missing(): void
    {
        $http = new MockHttp(MockHttp::token(), $this->simulationResponse(null));
        $api = $this->api($http);

        $this->expectException(VopConfirmationRequiredException::class);

        $api->execute($this->payment(), $api->simulate($this->payment()));
    }

    #[Test]
    public function it_executes_without_a_full_match_when_the_vop_outcome_is_accepted(): void
    {
        $http = new MockHttp(MockHttp::token(), $this->simulationResponse('NMTC'), MockHttp::fixture('SctInstant/execution'));
        $api = $this->api($http);

        $execution = $api->execute($this->payment(), $api->simulate($this->payment()), acceptVopResult: true);

        self::assertSame(PaymentStatus::Executed, $execution->paymentStatus);
    }

    #[Test]
    public function it_refuses_to_execute_a_rejected_simulation(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::envelope([
            'validationStatus' => 'RJCT rejected',
            'transactionStatusDescription' => 'Creditor IBAN not reachable',
        ], ['vopMatchResult' => 'MTCH']));
        $api = $this->api($http);
        $simulation = $api->simulate($this->payment());

        self::assertFalse($simulation->isAccepted());
        self::assertSame(ValidationStatus::Rejected, $simulation->validationStatus);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('RJCT rejected');

        $api->execute($this->payment(), $simulation, acceptVopResult: true);
    }

    #[Test]
    public function it_refuses_a_simulation_of_another_payment(): void
    {
        $http = new MockHttp(MockHttp::token(), $this->simulationResponse('MTCH'));
        $api = $this->api($http);
        $simulation = $api->simulate($this->payment());

        foreach ([
            'different amount' => $this->payment(amount: 150.26),
            'different creditor' => $this->payment(creditorIban: 'IT00X0000000000000000000003'),
        ] as $case => $otherPayment) {
            try {
                $api->execute($otherPayment, $simulation);
                self::fail("InvalidRequestException was not thrown for a {$case}");
            } catch (InvalidRequestException $exception) {
                self::assertStringContainsString('simulation belongs to a payment with a different', $exception->getMessage());
            }
        }

        self::assertSame(2, $http->count());
    }

    /**
     * Q-36 and F-03: a pending payment is resubmitted with "resubmit" and "resubmitId".
     */
    #[Test]
    public function it_resubmits_a_pending_payment(): void
    {
        $http = new MockHttp(
            MockHttp::token(),
            $this->simulationResponse('MTCH'),
            MockHttp::fixture('SctInstant/execution-pending'),
            MockHttp::fixture('SctInstant/execution'),
        );
        $api = $this->api($http);
        $simulation = $api->simulate($this->payment());

        $pending = $api->execute($this->payment(), $simulation);

        self::assertSame(PaymentStatus::Pending, $pending->paymentStatus);
        self::assertTrue($pending->canBeResubmitted());
        self::assertFalse($pending->paymentStatus->isFinal());

        $resubmitted = $api->resubmit($this->payment(), 'SIM-0001', (string) $pending->resubmitId);

        $body = $http->jsonBody(3);
        self::assertSame('SIM-0001', $body['simulationId'] ?? null);
        self::assertTrue($body['resubmit'] ?? null);
        self::assertSame('RES-0002', $body['resubmitId'] ?? null);
        self::assertSame(PaymentStatus::Executed, $resubmitted->paymentStatus);
    }

    /**
     * A simulation saved in one process (e.g. a daemon step) can be executed in another one.
     */
    #[Test]
    public function it_executes_a_simulation_restored_from_storage(): void
    {
        $http = new MockHttp(MockHttp::token(), $this->simulationResponse('CMTC'), MockHttp::fixture('SctInstant/execution'));
        $api = $this->api($http);

        $stored = json_encode($api->simulate($this->payment())->toArray(), JSON_THROW_ON_ERROR);
        $data = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        $restored = InstantSimulation::fromArray($data);

        self::assertSame('SIM-0001', $restored->simulationId);
        self::assertSame(ValidationStatus::Accepted, $restored->validationStatus);
        self::assertSame(VopMatchResult::CloseMatch, $restored->vop->result);
        self::assertSame('2026-01-15T10:00:00+00:00', $restored->date?->format(DATE_ATOM));
        self::assertSame(self::CREDITOR_IBAN, $restored->creditor->iban);

        try {
            $api->execute($this->payment(), $restored);
            self::fail('The VoP check must survive storage');
        } catch (VopConfirmationRequiredException) {
            $this->addToAssertionCount(1);
        }

        self::assertSame('ORD-0001', $api->execute($this->payment(), $restored, acceptVopResult: true)->orderId);
    }

    #[Test]
    public function it_reads_the_status_of_a_payment(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::fixture('SctInstant/status'));

        $status = $this->api($http)->status('ORD-0001', 'PAY-0001');

        self::assertSame(self::BASE.'/payments/sct/instant/'.self::IBAN.'/history/ORD-0001?paymentId=PAY-0001', (string) $http->request(1)->getUri());
        self::assertSame('ORD-0001', $status->orderId, 'Q-13: "order-id" variant');
        self::assertSame(InstantStatus::Canceled, $status->status, 'Q-30: "Cancelled" in Live');
        self::assertSame('Cancelled', $status->rawStatus);
        self::assertSame('IT', $status->creditor->nation);
        self::assertSame('Via Esempio 1, Roma', $status->creditor->address);
    }

    /**
     * Q-42: all the pages are collected and "paymentDirection" is kept on every page.
     */
    #[Test]
    public function it_lists_the_payments_of_a_date_range(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::fixture('SctInstant/list-page-1'), MockHttp::fixture('SctInstant/list-page-2'));

        $list = $this->api($http)->payments(new DateTimeImmutable('2025-12-15'), new DateTimeImmutable('2026-01-15'));

        self::assertSame(
            self::BASE.'/payments/sct/instant/'.self::IBAN.'/history?fromDate=20251215&offset=0&limit=100&paymentDirection=O&toDate=20260115',
            (string) $http->request(1)->getUri(),
        );
        self::assertSame(
            self::BASE.'/payments/sct/instant/'.self::IBAN.'/history?offset=2&limit=2&paymentDirection=O',
            (string) $http->request(2)->getUri(),
        );

        self::assertSame(['ORD-0001', 'ORD-0002', 'ORD-0003'], array_map(static fn (InstantPaymentListItem $p): ?string => $p->orderId, $list->payments));
        self::assertSame(3, $list->totalCount);
        self::assertTrue($list->isComplete());
        self::assertSame('2025-12-15', $list->inquiryFromDate?->format('Y-m-d'));

        [$first, $second, $third] = $list->payments;
        self::assertSame('2026-01-02', $first->valueDate?->format('Y-m-d'), 'Value dates are d/m/Y');
        self::assertSame(InstantStatus::Completed, $first->status);
        self::assertSame('CASH', $first->categoryPurpose);
        self::assertNull($first->remittanceInformation);
        self::assertSame(InstantStatus::Rejected, $second->status);
        self::assertSame(InstantStatus::Returned, $third->status);
        self::assertSame('Rimborso', $third->remittanceInformation);
    }

    #[Test]
    public function it_stops_listing_on_an_empty_page(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::envelope([
            'paymentsTotalCount' => 0,
            'links' => ['next' => self::BASE.'/payments/sct/instant/'.self::IBAN.'/history?offset=100&limit=100'],
            'payments' => [],
        ]));

        $list = $this->api($http)->payments(new DateTimeImmutable('2026-01-01'));

        self::assertSame([], $list->payments);
        self::assertSame(2, $http->count());
        self::assertStringNotContainsString('toDate', (string) $http->request(1)->getUri());
    }

    /**
     * Q-44: the sandbox list links point to localhost and to a placeholder IBAN.
     */
    #[Test]
    public function it_follows_the_sandbox_list_links(): void
    {
        $http = new MockHttp(
            MockHttp::token(),
            MockHttp::envelope([
                'links' => ['next' => 'http://localhost:8081/v1/payments/sct/instant/IT59R0306901001100000002110/history?offset=1&limit=1&paymentDirection=O'],
                'payments' => [['orderId' => 'ORD-1', 'amount' => 1]],
            ]),
            MockHttp::envelope(['payments' => []]),
        );

        $this->api($http, Environment::Sandbox)->payments(new DateTimeImmutable('2026-01-01'));

        self::assertSame(
            'https://external-api.intesasanpaolo.com/twa/sandbox/v1/payments/sct/instant/'.self::IBAN.'/history?offset=1&limit=1&paymentDirection=O',
            (string) $http->request(2)->getUri(),
        );
    }

    private function payment(
        string $creditorIban = self::CREDITOR_IBAN,
        float $amount = 150.25,
        ?string $debtorIban = null,
        ?string $endToEndId = null,
        ?string $siaCode = null,
    ): InstantPayment {
        return new InstantPayment('Mario Rossi', $creditorIban, $amount, 'Fattura 1/2026', 'Example Srl', $debtorIban, $endToEndId, $siaCode);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function simulationResponse(?string $vopMatchResult, array $headers = []): Response
    {
        if ($vopMatchResult !== null) {
            $headers['vopMatchResult'] = $vopMatchResult;
        }

        return MockHttp::fixture('SctInstant/simulation', $headers);
    }

    private function api(MockHttp $http, Environment $environment = Environment::Live): SctInstantApi
    {
        return (new IntesaSanPaoloClient($this->config($environment), $http->client))->sctInstant();
    }
}
