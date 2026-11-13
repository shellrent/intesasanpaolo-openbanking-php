<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Integration;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Config\Environment;
use Shellrent\OpenBanking\IntesaSanPaoloClient;

/**
 * Read-only checks against Live with the production credentials (D-55): balances, transactions,
 * payment lists and statuses, IBAN tools. Nothing here moves money, and the base class blocks any
 * request but GET before it leaves.
 *
 * Run with ISP_ENVIRONMENT=live, ISP_LIVE_READ_ONLY=yes and ISP_IBAN (docs/testing.md). The assertions
 * never compare real data with expected values, so that failures do not print it.
 */
final class LiveReadOnlyTest extends IntegrationTestCase
{
    protected static function environment(): Environment
    {
        return Environment::Live;
    }

    protected static function requiredSettings(): array
    {
        return ['ISP_IBAN'];
    }

    #[Test]
    public function it_reads_the_balance(): void
    {
        $balance = $this->live()->accounts()->balance();

        self::assertNotNull($balance->availableBalance);
        self::assertNotNull($balance->inquiryDate);
        self::assertNotNull($balance->executionDate);
    }

    #[Test]
    public function it_reads_the_transactions_of_today(): void
    {
        $list = $this->live()->accounts()->todayTransactions();

        self::assertTrue($list->isComplete());
        self::assertNotNull($list->executionDate);
    }

    #[Test]
    public function it_reads_the_transactions_of_the_last_weekday(): void
    {
        $day = new DateTimeImmutable('yesterday');

        while ((int) $day->format('N') > 5) {
            $day = $day->modify('-1 day');
        }

        $list = $this->live()->accounts()->transactions($day);

        self::assertTrue($list->isComplete());

        foreach ($list->accounted as $transaction) {
            self::assertNotNull($transaction->status, 'Q-23: the status is known');
        }
    }

    #[Test]
    public function it_lists_the_instant_payments(): void
    {
        $list = $this->live()->sctInstant()->payments();

        self::assertTrue($list->isComplete());
    }

    /**
     * Q-29: the status of a listed payment can be read by CRO.
     */
    #[Test]
    public function it_lists_the_sct_payments_and_reads_a_status(): void
    {
        $list = $this->live()->sct()->payments(new DateTimeImmutable('-30 days'));

        self::assertTrue($list->isComplete());

        foreach ($list->payments as $payment) {
            if ($payment->customerCro !== null) {
                $details = $this->live()->sct()->status(customerCro: $payment->customerCro);

                self::assertSame($payment->paymentId, $details->paymentId);

                return;
            }
        }
    }

    #[Test]
    public function it_checks_the_own_iban(): void
    {
        $iban = self::requireSetting('ISP_IBAN');

        self::assertTrue($this->live()->ibanTools()->formalCheck($iban)->valid);
        self::assertTrue($this->live()->sctInstant()->reachability($iban)->isReachable());
    }

    /**
     * Needs ISP_VAT_NUMBER (or ISP_FISCAL_CODE) of the account holder.
     */
    #[Test]
    public function it_verifies_the_holder_of_the_own_iban(): void
    {
        $vatNumber = self::setting('ISP_VAT_NUMBER');
        $fiscalCode = self::setting('ISP_FISCAL_CODE');

        if ($vatNumber === null && $fiscalCode === null) {
            self::markTestSkipped('Set ISP_VAT_NUMBER or ISP_FISCAL_CODE of the account holder.');
        }

        $verification = $this->live()->ibanTools()->verify(self::requireSetting('ISP_IBAN'), $fiscalCode, $vatNumber);

        self::assertTrue($verification->isAllowed());
    }

    private function live(): IntesaSanPaoloClient
    {
        return $this->client(self::requireSetting('ISP_IBAN'));
    }
}
