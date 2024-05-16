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
	 * @var float|null
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
		
		$this->Currency = ( !isset( $payload->currency ) or empty( $payload->currency ) ) ? null : (string)$payload->currency;
		$this->AvailableBalance = ( !isset( $payload->availableBalance ) or is_null( $payload->availableBalance ) )  ? null : (float)$payload->availableBalance;
		$this->AccountingBalance = ( !isset( $payload->accountingBalance ) or is_null( $payload->accountingBalance ) ) ? null : (float)$payload->accountingBalance;
		$this->CreditLine = ( !isset( $payload->creditLine ) or is_null( $payload->creditLine ) ) ? null : (float)$payload->creditLine;
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
