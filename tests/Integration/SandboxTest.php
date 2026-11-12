<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Integration;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Config\Environment;
use Shellrent\OpenBanking\Enum\InstantStatus;
use Shellrent\OpenBanking\Enum\PaymentStatus;
use Shellrent\OpenBanking\Exception\ApiException;
use Shellrent\OpenBanking\Exception\UnexpectedResponseException;
use Shellrent\OpenBanking\Request\InstantPayment;
use Shellrent\OpenBanking\Response\SctInstant\InstantSimulation;

/**
 * Test cases of the sandbox (docs/api-quirks.md, Q-51), with their fictitious data.
 *
 * The sandbox answers only the exact inputs of its test cases. Most simulation and execution cases use
 * a 36-character end-to-end ID, which the SDK refuses (SEPA allows 35, D-56): they are not covered here.
 *
 * Run with ISP_ENVIRONMENT=sandbox and the sandbox credentials (docs/testing.md).
 */
final class SandboxTest extends IntegrationTestCase
{
    private const string X1 = 'IT04G0306901000XTESTXCASEX1';

    private const string X2 = 'IT94H0306901000XTESTXCASEX2';

    private const string X5 = 'IT04G0306901000XTESTXCASEX5';

    private const string SCT_STATUS_IBAN = 'IT60X0306911101000000765432';

    protected static function environment(): Environment
    {
        return Environment::Sandbox;
    }

    #[Test]
    public function it_reads_a_balance(): void
    {
        $balance = $this->client('IT10B0306901000100000005479')->accounts()->balance(new DateTimeImmutable('2018-01-31'));

        self::assertSame('EUR', $balance->currency);
        self::assertSame(250000.0, $balance->availableBalance);
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
     * Q-27, Q-36: the pending test case answers "PDNG" with a resubmit ID.
     */
    #[Test]
    public function it_executes_an_instant_payment_that_stays_pending(): void
    {
        $payment = new InstantPayment('Mr. Receiver', self::X2, 20, 'SCT instant payment', 'Mr. Sender', null, '02INTER20180305HSRT1590814298', '');
        $simulation = InstantSimulation::fromArray([
            'validationStatus' => 'ACCP',
            'simulationId' => '123456789',
            'amount' => 20,
            'creditor' => ['iban' => self::X2],
            'vop' => ['rawResult' => 'MATCH'],
        ]);

        $execution = $this->client(self::X1)->sctInstant()->execute($payment, $simulation);

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
     * Q-51: the sandbox requires a fiscal code or a VAT number (D-51).
     */
    #[Test]
    public function it_requires_a_tax_code_to_verify_an_iban_in_the_sandbox(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('WI05');

        $this->client(self::X1)->ibanTools()->verify(self::X1);
    }
}
