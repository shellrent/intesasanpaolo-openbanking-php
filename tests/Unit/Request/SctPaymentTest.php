<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Request;

use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Exception\InvalidRequestException;
use Shellrent\OpenBanking\Request\SctPayment;
use Shellrent\OpenBanking\Request\Validator;
use Shellrent\OpenBanking\Tests\Unit\TestCase;

#[CoversClass(SctPayment::class)]
#[CoversClass(Validator::class)]
final class SctPaymentTest extends TestCase
{
    /**
     * Q-38: SCT is in euro; 2.x always sent the "CASH" category purpose.
     */
    #[Test]
    public function it_applies_the_defaults(): void
    {
        $payment = new SctPayment('Fornitore Esempio Srl', self::IBAN, 1250, 'Fattura 2/2026', 'Example Srl');

        self::assertSame('EUR', $payment->currency);
        self::assertSame('CASH', $payment->categoryPurpose);
        self::assertNull($payment->requestedExecutionDate);
        self::assertNull($payment->debtorIban);
        self::assertNull($payment->endToEndId);
    }

    #[Test]
    public function it_normalizes_the_values(): void
    {
        $date = new DateTime('2026-01-20 15:00:00');

        $payment = new SctPayment(
            creditorName: 'Fornitore Esempio Srl',
            creditorIban: 'it00 x000 0000 0000 0000 0000 002',
            amount: 1250.1 + 0.2,
            paymentInformation: 'Fattura 2/2026',
            debtorName: 'Example Srl',
            requestedExecutionDate: $date,
            endToEndId: 'NOTPROVIDED',
            categoryPurpose: ' supp ',
            currency: 'eur',
        );

        $date->modify('+1 day');

        self::assertSame('IT00X0000000000000000000002', $payment->creditorIban);
        self::assertSame(1250.3, $payment->amount);
        self::assertInstanceOf(DateTimeImmutable::class, $payment->requestedExecutionDate);
        self::assertSame('2026-01-20', $payment->requestedExecutionDate->format('Y-m-d'), 'Later changes to the given DateTime do not leak in');
        self::assertNull($payment->endToEndId);
        self::assertSame('SUPP', $payment->categoryPurpose);
        self::assertSame('EUR', $payment->currency);
    }

    #[Test]
    public function it_rejects_an_invalid_category_purpose(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('category purpose must be a code of 4 letters');

        new SctPayment('Fornitore', self::IBAN, 10, 'Fattura', 'Example Srl', categoryPurpose: 'CASH1');
    }

    #[Test]
    public function it_rejects_an_invalid_currency(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('currency must be a code of 3 letters');

        new SctPayment('Fornitore', self::IBAN, 10, 'Fattura', 'Example Srl', currency: 'EURO');
    }

    #[Test]
    public function it_shares_the_validation_rules_of_instant_payments(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('more than two decimals');

        new SctPayment('Fornitore', self::IBAN, 10.001, 'Fattura', 'Example Srl');
    }
}
