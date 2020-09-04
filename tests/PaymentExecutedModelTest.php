<?php

namespace Shellrent\OpenBanking\Tests;

use Shellrent\OpenBanking\Models\PaymentExecuted;
use stdClass;

class PaymentExecutedModelTest extends TestCase {

	/**
	 * Given a fake answer, check that PaymentExecuted model is built correctly
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

		$fakeResponse->date = $date = $this->faker()->dateTime->format( 'Y-m-d H:i:s' );
		$fakeResponse->categoryPurpose = $categoryPurpose = $this->faker()->word();
		
		$fakeResponse->debtorBic = $debtorBic = $this->faker()->swiftBicNumber;
		$fakeResponse->creditorBic = $creditorBic = $this->faker()->swiftBicNumber;
		
		$fakeResponse->paymentStatus = $paymentStatus = $this->faker()->randomElement(  ['ACSC', 'PNDG', 'RJCT'] );
		$fakeResponse->transactionStatusDescription = $transactionStatusDescription = $this->faker()->realText();
		$fakeResponse->customerCro = $customerCro = $this->faker()->randomNumber();

		$paymentExecuted = new PaymentExecuted( $fakeResponse );

		$this->assertEquals( $orderId, $paymentExecuted->getOrderId() );
		$this->assertEquals( $paymentId, $paymentExecuted->getPaymentId() );
		$this->assertEquals( $debtorName, $paymentExecuted->getDebtorName() );
		$this->assertEquals( $debtorIban, $paymentExecuted->getDebtorIban() );
		$this->assertEquals( $creditorName, $paymentExecuted->getCreditorName() );
		$this->assertEquals( $creditorIban, $paymentExecuted->getCreditorIban() );
		$this->assertEquals( $amount, $paymentExecuted->getAmount() );
		$this->assertEquals( $currency, $paymentExecuted->getCurrency() );
		$this->assertEquals( $ultimateDebtorName, $paymentExecuted->getUltimateDebtorName() );
		$this->assertEquals( $ultimateCreditorName, $paymentExecuted->getUltimateCreditorName() );
		$this->assertEquals( $date, $paymentExecuted->getDate()->format( 'Y-m-d H:i:s' ) );
		$this->assertEquals( $categoryPurpose, $paymentExecuted->getCategoryPurpose() );
		$this->assertEquals( $debtorBic, $paymentExecuted->getDebtorBic() );
		$this->assertEquals( $creditorBic, $paymentExecuted->getCreditorBic() );
		$this->assertEquals( $paymentStatus, $paymentExecuted->getPaymentStatus() );
		$this->assertEquals( $transactionStatusDescription, $paymentExecuted->getTransactionStatusDescription() );
		$this->assertEquals( $customerCro, $paymentExecuted->getCustomerCro() );

	}
}