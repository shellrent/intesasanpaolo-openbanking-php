<?php

namespace Shellrent\OpenBanking\Tests;

use Shellrent\OpenBanking\Models\Transaction;
use stdClass;

class TransactionModelTest extends TestCase {

	/**
	 * Given a fake answer, check that Transaction model is built correctly
	 * @test
	 */
	public function createPaymentExecutedByFakeResponse() {
		$fakeResponse = new stdClass();

		$fakeResponse->uniqueId = $uniqueId = uniqid();
		$fakeResponse->reference = $reference = $this->faker()->numberBetween();
		$fakeResponse->date = $date = $this->faker()->dateTime->format( 'Y-m-d H:i:s' );
		$fakeResponse->accountingDate = $accountingDate = $this->faker()->dateTime->format( 'Y-m-d H:i:s' );
		$fakeResponse->valueDate = $valueDate = $this->faker()->dateTime->format( 'Y-m-d H:i:s' );
		$fakeResponse->currency = $currency = $this->faker()->currencyCode;
		$fakeResponse->amount = $amount = $this->faker()->randomFloat( 2 );

		$fakeResponse->originalCurrency = $originalCurrency = $this->faker()->currencyCode;
		$fakeResponse->originalAmount = $originalAmount = $this->faker()->randomFloat( 2 );
		$fakeResponse->exchangeRate = $exchangeRate = $this->faker()->randomFloat( 2 );

		$fakeResponse->type = $type = $this->faker()->word();
		$fakeResponse->description = $description = $this->faker()->realText();
		$fakeResponse->additionalInfo = $reason = $this->faker()->realText();
		$fakeResponse->status = $status = $this->faker()->word();

		$transaction = new Transaction( $fakeResponse );

		$this->assertEquals( $uniqueId, $transaction->getUniqueId() );
		$this->assertEquals( $reference, $transaction->getReference() );
		$this->assertEquals( $date, $transaction->getDate()->format( 'Y-m-d H:i:s' ) );
		$this->assertEquals( $accountingDate, $transaction->getAccountingDate()->format( 'Y-m-d H:i:s' ) );
		$this->assertEquals( $valueDate, $transaction->getValueDate()->format( 'Y-m-d H:i:s' ) );
		$this->assertEquals( $currency, $transaction->getCurrency() );
		$this->assertEquals( $amount, $transaction->getAmount() );
		$this->assertEquals( $originalCurrency, $transaction->getOriginalCurrency() );
		$this->assertEquals( $originalAmount, $transaction->getOriginalAmount() );
		$this->assertEquals( $exchangeRate, $transaction->getExchangeRate() );
		$this->assertEquals( $type, $transaction->getType() );
		$this->assertEquals( $description, $transaction->getDescription() );
		$this->assertEquals( $reason, $transaction->getReason() );
		$this->assertEquals( $status, $transaction->getStatus() );
	}
}