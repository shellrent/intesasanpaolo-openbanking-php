<?php

namespace Shellrent\OpenBanking\Tests;

use Shellrent\OpenBanking\Models\Balance;
use stdClass;

class BalanceModelTest extends TestCase {
	/**
	 * Given a fake answer, check that Balance model is built correctly
	 * @test
	 */
	public function createBalanceByFakeResponse() {
		$fakeResponse = new stdClass();
		$fakeResponse->payload = new stdClass();
		$fakeResponse->payload->currency = $currency = $this->faker()->currencyCode;
		$fakeResponse->payload->availableBalance = $availableBalance  = $this->faker()->randomFloat( 2 );
		$fakeResponse->payload->accountingBalance = $accountingBalance = $this->faker()->randomFloat( 2 );
		$fakeResponse->payload->creditLine = $creditLine = $this->faker()->randomFloat( 2 );
		
		$balance = new Balance( $fakeResponse );
		
		$this->assertEquals( $currency, $balance->getCurrency() );
		$this->assertEquals( $availableBalance, $balance->getAvailableBalance() );
		$this->assertEquals( $accountingBalance, $balance->getAccountingBalance() );
		$this->assertEquals( $creditLine, $balance->getCreditLine() );
	}
}
