<?php

namespace Shellrent\OpenBanking\Models;

use stdClass;

class Balance extends GenericModel {
	/**
	 * @var string|null
	 */
	private $Currency;
	
	/**
	 * @var float|null
	 */
	private $AvailableBalance;
	
	/**
	 * @var float
	 */
	private $AccountingBalance;
	
	/**
	 * @var float|null
	 */
	private $CreditLine;
	
	
	
	/**
	 * Hydrates specific data
	 * @param stdClass $data
	 */
	protected function hydrateData( stdClass $data ) {
		$payload = $data->payload;
		
		$this->Currency = is_null( $payload->currency ) ? null : (string)$payload->currency;
		$this->AvailableBalance = is_null( $payload->availableBalance ) ? null : (float)$payload->availableBalance;
		$this->AccountingBalance = (float)$payload->accountingBalance;
		$this->CreditLine = is_null( $payload->creditLine ) ? null : (float)$payload->creditLine;
	}
	
	
	/**
	 * @return string
	 */
	public function getCurrency(): ?string {
		return $this->Currency;
	}
	
	
	/**
	 * @return float
	 */
	public function getAvailableBalance(): ?float {
		return $this->AvailableBalance;
	}
	
	
	/**
	 * @return float
	 */
	public function getAccountingBalance(): ?float {
		return $this->AccountingBalance;
	}
	
	
	/**
	 * @return float
	 */
	public function getCreditLine(): ?float {
		return $this->CreditLine;
	}
}
