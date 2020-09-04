<?php

namespace Shellrent\OpenBanking\Tests;

use Shellrent\OpenBanking\Models\PaymentInfo;
use stdClass;

class PaymentInfoModelTest extends TestCase {

	/**
	 * Given a fake answer, check that PaymentInfo model is built correctly
	 * @test
	 */
	public function createPaymentExecutedByFakeResponse() {
		$fakeResponse = new stdClass();

		$fakeResponse->{'order-id'} = $orderId = $this->faker()->randomNumber();
		$fakeResponse->{'payment-id'} = $paymentId = $this->faker()->randomNumber();

		$fakeResponse->debtorName = $debtorName = $this->faker()->name;
		$fakeResponse->debtorIBAN = $debtorIban = $this->faker()->iban();

		$fakeResponse->creditorName = $creditorName = $this->faker()->name;
		$fakeResponse->creditorIBAN = $creditorIban = $this->faker()->iban();

		$fakeResponse->amount = $amount = $this->faker()->randomFloat( 2 );
		$fakeResponse->currency = $currency = $this->faker()->currencyCode;

		$fakeResponse->ultimateDebtorName = $ultimateDebtorName = $this->faker()->name;
		$fakeResponse->ultimateCreditorName = $ultimateCreditorName = $this->faker()->name;

		$fakeResponse->status = $status = $this->faker()->randomElement(  [ 'Completed', 'Canceled', 'Rejected', 'Returned' ] );
		$fakeResponse->remittanceInformation = $remittanceInformation = $this->faker()->realText();


		$paymentInfo = new PaymentInfo( $fakeResponse );

		$this->assertEquals( $orderId, $paymentInfo->getOrderId() );
		$this->assertEquals( $paymentId, $paymentInfo->getPaymentId() );
		$this->assertEquals( $debtorName, $paymentInfo->getDebtorName() );
		$this->assertEquals( $debtorIban, $paymentInfo->getDebtorIban() );
		$this->assertEquals( $creditorName, $paymentInfo->getCreditorName() );
		$this->assertEquals( $creditorIban, $paymentInfo->getCreditorIban() );
		$this->assertEquals( $amount, $paymentInfo->getAmount() );
		$this->assertEquals( $currency, $paymentInfo->getCurrency() );
		$this->assertEquals( $ultimateDebtorName, $paymentInfo->getUltimateDebtorName() );
		$this->assertEquals( $ultimateCreditorName, $paymentInfo->getUltimateCreditorName() );
		$this->assertEquals( $status, $paymentInfo->getStatus() );
		$this->assertEquals( $remittanceInformation, $paymentInfo->getRemittanceInformation() );
	}
}