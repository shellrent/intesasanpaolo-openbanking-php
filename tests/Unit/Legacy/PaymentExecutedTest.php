<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Legacy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Models\GenericPaymentData;
use Shellrent\OpenBanking\Models\GenericPaymentResult;
use Shellrent\OpenBanking\Models\PaymentExecuted;

#[CoversClass(PaymentExecuted::class)]
#[CoversClass(GenericPaymentResult::class)]
#[CoversClass(GenericPaymentData::class)]
final class PaymentExecutedTest extends LegacyTestCase
{
    #[Test]
    public function it_hydrates_an_instant_payment_execution(): void
    {
        $payment = new PaymentExecuted(self::decoded([
            'success' => true,
            'errors' => null,
            'payload' => [
                'orderId' => 'ORD-0001',
                'paymentId' => 'PAY-0001',
                'endToEndId' => 'E2E-0001',
                'resubmitId' => 'RES-0001',
                'customerCro' => 'CRO-0001',
                'transactionStatusDescription' => 'ACSC-Prtry:',
                'paymentStatus' => 'ACSC',
                'amount' => 250,
                'currency' => 'EUR',
                'date' => '2021-03-23T10:15:30Z',
                'categoryPurpose' => 'CASH',
                'paymentInformation' => 'Fattura 1/2021',
                'debtorName' => 'Example Srl',
                'debtorIBAN' => 'IT00X0000000000000000000001',
                'debtorBic' => 'BCITITMM',
                'ultimateDebtorName' => 'Example Srl',
                'creditorName' => 'Mario Rossi',
                'creditorIBAN' => 'IT00X0000000000000000000002',
                'creditorBic' => 'BCITITMM',
                'ultimateCreditorName' => 'Mario Rossi',
            ],
        ]));

        self::assertSame('ORD-0001', $payment->getOrderId());
        self::assertSame('PAY-0001', $payment->getPaymentId());
        self::assertSame('E2E-0001', $payment->getEndToEndId());
        self::assertSame('RES-0001', $payment->getResubmitId());
        self::assertSame('CRO-0001', $payment->getCustomerCro());
        self::assertSame('ACSC-Prtry:', $payment->getTransactionStatusDescription());
        self::assertSame('ACSC', $payment->getPaymentStatus());
        self::assertSame(250.0, $payment->getAmount());
        self::assertSame('EUR', $payment->getCurrency());
        self::assertSame('2021-03-23T10:15:30+00:00', $payment->getDate()?->format(DATE_ATOM));
        self::assertSame('CASH', $payment->getCategoryPurpose());
        self::assertSame('Fattura 1/2021', $payment->getPaymentInformation());
        self::assertSame('Example Srl', $payment->getDebtorName());
        self::assertSame('IT00X0000000000000000000001', $payment->getDebtorIban());
        self::assertSame('BCITITMM', $payment->getDebtorBic());
        self::assertSame('Example Srl', $payment->getUltimateDebtorName());
        self::assertSame('Mario Rossi', $payment->getCreditorName());
        self::assertSame('IT00X0000000000000000000002', $payment->getCreditorIban());
        self::assertSame('BCITITMM', $payment->getCreditorBic());
        self::assertSame('Mario Rossi', $payment->getUltimateCreditorName());
    }

    /**
     * Q-14, Q-15, Q-16: SCT uses "customerCRO" and "commissioni", and dates in d/m/Y format.
     */
    #[Test]
    public function it_hydrates_an_sct_payment_execution(): void
    {
        $payment = new PaymentExecuted(self::decoded([
            'success' => true,
            'errors' => null,
            'payload' => [
                'customerCRO' => 'CRO-0002',
                'paymentId' => 'PAY-0002',
                'transactionStatusDescription' => 'Accepted',
                'paymentStatus' => 'ACSP',
                'creditorBic' => 'BCITITMM',
                'commissioni' => 0.9,
                'valueDate' => '13/01/2022',
                'settlementDate' => '14/01/2022',
                'revokeDate' => '13/01/2022',
                'revokeTime' => '17:30:00',
            ],
        ]));

        self::assertSame('CRO-0002', $payment->getCustomerCro());
        self::assertSame('PAY-0002', $payment->getPaymentId());
        self::assertSame('Accepted', $payment->getTransactionStatusDescription());
        self::assertSame('ACSP', $payment->getPaymentStatus());
        self::assertSame(0.9, $payment->getCommissions());
        self::assertSame('2022-01-13', $payment->getValueDate()?->format('Y-m-d'));
        self::assertSame('2022-01-14', $payment->getSettlementDate()?->format('Y-m-d'));
        self::assertSame('2022-01-13 17:30:00', $payment->getRevokeDate()?->format('Y-m-d H:i:s'));
        self::assertSame('17:30:00', $payment->getRevokeTime());
    }
}
