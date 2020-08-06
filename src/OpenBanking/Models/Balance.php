<?php

namespace Shellrent\OpenBanking\Models;

use stdClass;

use Shellrent\OpenBanking\Models\GenericModel;


class Balance extends GenericModel {
	/**
	 * @var string
	 */
	private $Currency;
	
	/**
	 * @var float
	 */
	private $AvailableBalance;
	
	/**
	 * @var float
	 */
	private $AccountingBalance;
	
	/**
	 * @var float
	 */
	private $CreditLine;
	
	
	
	/**
	 * Hydrates specific data
	 * @param stdClass $data
	 */
	protected function hydrateData( stdClass $data ) {
		$payload = $data->payload;
		
		$this->Currency = (string)$payload->currency;
		$this->AvailableBalance = (float)$payload->availableBalance;
		$this->AccountingBalance = (float)$payload->accountingBalance;
		$this->CreditLine = (float)$payload->creditLine;
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
