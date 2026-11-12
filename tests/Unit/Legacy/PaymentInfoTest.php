<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Legacy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Models\GenericPaymentData;
use Shellrent\OpenBanking\Models\GenericPaymentInfo;
use Shellrent\OpenBanking\Models\PaymentInfo;

#[CoversClass(PaymentInfo::class)]
#[CoversClass(GenericPaymentInfo::class)]
#[CoversClass(GenericPaymentData::class)]
final class PaymentInfoTest extends LegacyTestCase
{
    #[Test]
    public function it_hydrates_an_instant_payments_list_item(): void
    {
        $payment = new PaymentInfo(self::decoded([
            'orderId' => 'ORD-0001',
            'paymentId' => 'PAY-0001',
            'status' => 'Completed',
            'amount' => 99.9,
            'debtorName' => 'Example Srl',
            'debtorIBAN' => 'IT00X0000000000000000000001',
            'ultimateDebtorName' => null,
            'creditorName' => 'Mario Rossi',
            'creditorIBAN' => 'IT00X0000000000000000000002',
            'ultimateCreditorName' => null,
            'remittanceInformation' => 'Rimborso',
            'valueDate' => '13/12/2018',
            'categoryPurpose' => 'CASH',
        ]));

        self::assertSame('ORD-0001', $payment->getOrderId());
        self::assertSame('PAY-0001', $payment->getPaymentId());
        self::assertSame('Completed', $payment->getStatus());
        self::assertSame(99.9, $payment->getAmount());
        self::assertSame('Example Srl', $payment->getDebtorName());
        self::assertSame('IT00X0000000000000000000001', $payment->getDebtorIban());
        self::assertSame('Mario Rossi', $payment->getCreditorName());
        self::assertSame('IT00X0000000000000000000002', $payment->getCreditorIban());
        self::assertSame('Rimborso', $payment->getRemittanceInformation());
        self::assertSame('2018-12-13', $payment->getValueDate()?->format('Y-m-d'));
    }

    /**
     * Q-12 and Q-13: SCT uses "debtorIban", and older responses use "order-id" / "payment-id".
     */
    #[Test]
    public function it_reads_the_alternative_field_names(): void
    {
        $payment = new PaymentInfo(self::decoded([
            'order-id' => 'ORD-0002',
            'payment-id' => 'PAY-0002',
            'paymentStatus' => 'ACSC',
            'amount' => 10,
            'debtorIban' => 'IT00X0000000000000000000001',
            'creditorIban' => 'IT00X0000000000000000000002',
            'remittanceInformation' => null,
        ]));

        self::assertSame('ORD-0002', $payment->getOrderId());
        self::assertSame('PAY-0002', $payment->getPaymentId());
        self::assertSame('ACSC', $payment->getStatus());
        self::assertSame('IT00X0000000000000000000001', $payment->getDebtorIban());
        self::assertSame('IT00X0000000000000000000002', $payment->getCreditorIban());
        self::assertNull($payment->getRemittanceInformation());
    }
}
