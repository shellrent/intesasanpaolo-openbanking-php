<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Integration;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Config\Environment;
use Shellrent\OpenBanking\Enum\InstantStatus;
use Shellrent\OpenBanking\Enum\PaymentStatus;
use Shellrent\OpenBanking\Enum\ValidationStatus;
use Shellrent\OpenBanking\Enum\VopMatchResult;
use Shellrent\OpenBanking\Exception\UnexpectedResponseException;
use Shellrent\OpenBanking\Request\InstantPayment;
use Shellrent\OpenBanking\Request\SctPayment;
use Shellrent\OpenBanking\Response\Sct\SctSimulation;
use Shellrent\OpenBanking\Response\SctInstant\InstantSimulation;

/**
 * Test cases of the sandbox (docs/api-quirks.md, Q-51), with their fictitious data.
 *
 * The sandbox answers only the exact inputs of its test cases, including the 36-character end-to-end ID
 * of most simulation and execution cases (D-56). Executions in the sandbox move no money.
 *
 * Run with ISP_ENVIRONMENT=sandbox and the sandbox credentials (docs/testing.md).
 */
final class SandboxTest extends IntegrationTestCase
{
    private const string X1 = 'IT04G0306901000XTESTXCASEX1';

    private const string X2 = 'IT94H0306901000XTESTXCASEX2';

    private const string X5 = 'IT04G0306901000XTESTXCASEX5';

    private const string SCT_STATUS_IBAN = 'IT60X0306911101000000765432';

    private const string E2E = '4271ce32-ba61-41cb-954a-25cf762e9621';

    protected static function environment(): Environment
    {
        return Environment::Sandbox;
    }

    #[Test]
    public function it_reads_a_balance(): void
    {
        $balance = $this->client('IT10B0306901000100000005479')->accounts()->balance(new DateTimeImmutable('2018-01-31'));

        self::assertSame('EUR', $balance->currency);
        self::assertSame('250000.00', $balance->availableBalance, 'D-65: the sandbox sends the balance as a string');
        self::assertNotNull($balance->executionDate, 'Q-49: date-time with ":" before the milliseconds');
    }

    #[Test]
    public function it_reads_the_transactions_of_today(): void
    {
        $list = $this->client(self::X1)->accounts()->todayTransactions();

        self::assertTrue($list->isComplete());
        self::assertNotEmpty($list->accounted);
        self::assertNotEmpty($list->unaccounted);
    }

    /**
     * Q-41: the sandbox link is already complete, but the test case returns the same page forever.
     */
    #[Test]
    public function it_stops_on_the_repeating_link_of_the_end_of_day_transactions(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Pagination loop');

        $this->client(self::X1)->accounts()->transactions(new DateTimeImmutable('2018-02-01'));
    }

    /**
     * @return iterable<string, array{string, string, string, ValidationStatus, VopMatchResult|null, string|null}>
     */
    public static function instantSimulations(): iterable
    {
        yield 'match' => ['450', 'Mr. Receiver', 'SCT instant payment simulation', ValidationStatus::Accepted, VopMatchResult::Match, null];
        yield 'rejected, no VoP outcome' => ['24.8', 'Mr. Receiver', 'SCT instant payment simulation', ValidationStatus::Rejected, null, null];
        yield 'close match' => ['4.5', 'Paolo Bianchini', 'SCT instant payment simulation VOP CLOSE MATCH', ValidationStatus::Accepted, VopMatchResult::CloseMatch, 'Paolo Bianchi'];
        yield 'check not possible' => ['4.5', 'Paolo Bianchi', 'SCT instant payment simulation VOP ERROR', ValidationStatus::Accepted, VopMatchResult::NotPossible, null];
    }

    /**
     * Q-37: the sandbox sends the FAQ labels in the VoP headers, and the string "null" on a rejection (Q-50).
     */
    #[Test]
    #[DataProvider('instantSimulations')]
    public function it_simulates_an_instant_payment(
        string $amount,
        string $creditorName,
        string $information,
        ValidationStatus $status,
        ?VopMatchResult $vop,
        ?string $matchedName,
    ): void {
        $payment = new InstantPayment($creditorName, self::X2, $amount, $information, 'Mr. Sender', null, self::E2E, '');

        $simulation = $this->client(self::X1)->sctInstant()->simulate($payment);

        self::assertSame($status, $simulation->validationStatus);
        self::assertSame($vop, $simulation->vop->result);
        self::assertSame($matchedName, $simulation->vop->matchedName);
        self::assertSame($vop === null, ! $simulation->vop->wasPerformed());
    }

    /**
     * D-36, D-62: simulation and execution of the same test case, with a full VoP match.
     */
    #[Test]
    public function it_executes_a_simulated_instant_payment(): void
    {
        $client = $this->client(self::X1);
        $simulation = $client->sctInstant()->simulate(new InstantPayment('Mr. Receiver', self::X2, '450', 'SCT instant payment simulation', 'Mr. Sender', null, self::E2E, ''));
        $payment = new InstantPayment('Mr. Receiver', self::X2, '450', 'SCT instant payment', 'Mr. Sender', null, self::E2E, '');

        $execution = $client->sctInstant()->execute($payment, $simulation);

        self::assertSame(PaymentStatus::Executed, $execution->paymentStatus);
        self::assertNotNull($execution->orderId);
    }

    /**
     * @return iterable<string, array{string, PaymentStatus}>
     */
    public static function instantExecutions(): iterable
    {
        yield 'rejected' => ['1500', PaymentStatus::Rejected];
        yield 'to verify, with "null" strings (Q-50)' => ['330', PaymentStatus::ToVerify];
    }

    #[Test]
    #[DataProvider('instantExecutions')]
    public function it_reads_the_outcome_of_an_instant_execution(string $amount, PaymentStatus $expected): void
    {
        $payment = new InstantPayment('Mr. Receiver', self::X2, $amount, 'SCT instant payment', 'Mr. Sender', null, self::E2E, '');

        $execution = $this->client(self::X1)->sctInstant()->execute($payment, $this->instantSimulation($amount));

        self::assertSame($expected, $execution->paymentStatus);
        self::assertFalse($execution->canBeResubmitted());
    }

    /**
     * Q-27, Q-36: the pending test case answers "PDNG" with a resubmit ID.
     */
    #[Test]
    public function it_executes_an_instant_payment_that_stays_pending(): void
    {
        $payment = new InstantPayment('Mr. Receiver', self::X2, '20', 'SCT instant payment', 'Mr. Sender', null, '02INTER20180305HSRT1590814298', '');

        $execution = $this->client(self::X1)->sctInstant()->execute($payment, $this->instantSimulation('20'));

        self::assertSame(PaymentStatus::Pending, $execution->paymentStatus);
        self::assertSame('PDNG', $execution->rawPaymentStatus);
        self::assertTrue($execution->canBeResubmitted());
    }

    /**
     * @return iterable<string, array{string, InstantStatus}>
     */
    public static function instantStatuses(): iterable
    {
        yield 'Completed' => ['0306921767615104480100101001IT', InstantStatus::Completed];
        yield 'Cancelled' => ['8306921767615104480100101001IT', InstantStatus::Canceled];
        yield 'Rejected' => ['7306921767615104480100101001IT', InstantStatus::Rejected];
        yield 'Returned' => ['6306921767615104480100101001IT', InstantStatus::Returned];
    }

    #[Test]
    #[DataProvider('instantStatuses')]
    public function it_reads_the_status_of_an_instant_payment(string $orderId, InstantStatus $expected): void
    {
        $details = $this->client(self::X1)->sctInstant()->status($orderId);

        self::assertSame($expected, $details->status);
        self::assertSame($orderId, $details->orderId);
    }

    /**
     * Q-26, Q-37: SCT simulation with the VoP outcome; the test case requires the execution date (Q-38).
     */
    #[Test]
    public function it_simulates_an_sct_payment(): void
    {
        $accepted = $this->client(self::X5)->sct()->simulate($this->sctPayment('40', 'SCT payment simulation', 'IT94H0306901000XTESTXCASEX6'));
        $rejected = $this->client('IT04G0306901000XTESTXCASEX7')->sct()->simulate($this->sctPayment('50', 'SCT payment simulation', 'IT94H0306901000XTESTXCASEX8'));

        self::assertSame(ValidationStatus::Accepted, $accepted->validationStatus);
        self::assertSame(VopMatchResult::Match, $accepted->vop->result);
        self::assertNotNull($accepted->executionDate, 'Q-10: d/m/Y date');
        self::assertSame(ValidationStatus::Rejected, $rejected->validationStatus);
        self::assertFalse($rejected->vop->wasPerformed());
    }

    /**
     * @return iterable<string, array{string, string, string, string, string|null, PaymentStatus}>
     */
    public static function sctExecutions(): iterable
    {
        yield 'in progress' => [self::X5, 'IT94H0306901000XTESTXCASEX6', '20', '123456789123456789', null, PaymentStatus::InProgress];
        yield 'accepted, future date' => [self::X5, 'IT94H0306901000XTESTXCASEX6', '40', '123456789123456789', '2099-12-31', PaymentStatus::Accepted];
        yield 'to verify' => ['IT04G0306901000XTESTXCASEX9', 'IT94H0306901000XTESTXCASEX10', '50', '12349087654321', null, PaymentStatus::ToVerify];
    }

    /**
     * Q-28.
     */
    #[Test]
    #[DataProvider('sctExecutions')]
    public function it_reads_the_outcome_of_an_sct_execution(
        string $debtorIban,
        string $creditorIban,
        string $amount,
        string $simulationId,
        ?string $date,
        PaymentStatus $expected,
    ): void {
        $payment = $this->sctPayment($amount, 'SCT payment execution', $creditorIban, $date === null ? null : new DateTimeImmutable($date));
        $simulation = SctSimulation::fromArray([
            'validationStatus' => 'ACCP',
            'simulationId' => $simulationId,
            'amount' => $amount,
            'creditor' => ['iban' => $creditorIban, 'name' => 'Paolo Bianchi'],
            'vop' => ['rawResult' => 'MATCH'],
        ]);

        $execution = $this->client($debtorIban)->sct()->execute($payment, $simulation);

        self::assertSame($expected, $execution->paymentStatus);
    }

    #[Test]
    public function it_lists_the_instant_payments(): void
    {
        $list = $this->client(self::X1)->sctInstant()->payments(new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-03-06'));

        self::assertTrue($list->isComplete());
        self::assertNotEmpty($list->payments);
    }

    /**
     * @return iterable<string, array{string, array{customerCro?: string, paymentId?: string, endToEndId?: string}, PaymentStatus}>
     */
    public static function sctStatuses(): iterable
    {
        yield 'ACSP' => [self::X5, ['customerCro' => '0306900456071705484794123455IT', 'paymentId' => 'BS11113214639522514799ATE'], PaymentStatus::InProgress];
        yield 'CANC' => [self::SCT_STATUS_IBAN, ['customerCro' => '0306900456071705484794123456IT', 'paymentId' => 'BS22223214639522514799ATE'], PaymentStatus::Canceled];
        yield 'ACSC' => [self::SCT_STATUS_IBAN, ['customerCro' => '0306900456071705484794123457IT', 'paymentId' => 'BS33333214639522514799ATE'], PaymentStatus::Executed];
        yield 'RETU' => [self::SCT_STATUS_IBAN, ['customerCro' => '0306900456071705484794123459IT'], PaymentStatus::Returned];
        yield 'ACTC' => ['IT94G0306901000XTESTXCASEX11', ['endToEndId' => '12345678'], PaymentStatus::Accepted];
    }

    /**
     * @param  array{customerCro?: string, paymentId?: string, endToEndId?: string}  $identifiers
     */
    #[Test]
    #[DataProvider('sctStatuses')]
    public function it_reads_the_status_of_an_sct_payment(string $iban, array $identifiers, PaymentStatus $expected): void
    {
        $details = $this->client($iban)->sct()->status(...$identifiers);

        self::assertSame($expected, $details->paymentStatus);
    }

    /**
     * Q-31: the refused revocation arrives with "success: false" and is an outcome, not an error.
     */
    #[Test]
    public function it_revokes_an_sct_payment(): void
    {
        $revoked = $this->client(self::X5)->sct()->revoke('0306900456071705484794123455IT', 'BS11113214639522514799ATE');
        $refused = $this->client('IT04G0306901000XTESTXCASEX7')->sct()->revoke(paymentId: '123456789');

        self::assertTrue($revoked->isRevoked());
        self::assertFalse($refused->revoked);
    }

    #[Test]
    public function it_checks_an_iban(): void
    {
        $client = $this->client(self::X1);

        $check = $client->ibanTools()->formalCheck(self::X1);
        $reachability = $client->sctInstant()->reachability(self::X1);

        self::assertTrue($check->valid);
        self::assertSame('03069', $check->bankCode, 'Q-19: the parts are strings');
        self::assertTrue($reachability->isReachable());
    }

    /**
     * Q-20: positive and negative outcomes; the negative one arrives with "success: false".
     */
    #[Test]
    public function it_verifies_the_holder_of_an_iban(): void
    {
        $allowed = $this->client(self::X1)->ibanTools()->verify('IT08P0306903000XTESTXCASEX3', fiscalCode: 'HQAZHT71C51C268Z');
        $byVat = $this->client(self::X1)->ibanTools()->verify(self::X1, vatNumber: '97536750017');
        $refused = $this->client(self::X1)->ibanTools()->verify(self::X1, fiscalCode: 'HQAZHT71C51C300Z');

        self::assertTrue($allowed->isAllowed());
        self::assertTrue($byVat->isAllowed());
        self::assertFalse($refused->isAllowed());
        self::assertStringContainsString('vatNumber is mandatory', (string) $refused->additionalInfo);
    }

    /**
     * The execution test cases use the fixed simulation ID "123456789".
     */
    private function instantSimulation(string $amount): InstantSimulation
    {
        return InstantSimulation::fromArray([
            'validationStatus' => 'ACCP',
            'simulationId' => '123456789',
            'amount' => $amount,
            'creditor' => ['iban' => self::X2, 'name' => 'Mr. Receiver'],
            'vop' => ['rawResult' => 'MATCH'],
        ]);
    }

    private function sctPayment(string $amount, string $information, string $creditorIban, ?DateTimeImmutable $date = null): SctPayment
    {
        return new SctPayment('Paolo Bianchi', $creditorIban, $amount, $information, 'Mario Rossi', null, $date ?? new DateTimeImmutable('today'), self::E2E, 'CASH');
    }
}
