<?php

namespace Shellrent\OpenBanking\Models;

use DateTime;
use stdClass;

class Transaction extends GenericModel {
	/**
	 * @var string
	 */
	private $UniqueId;
	
	/**
	 * @var string
	 */
	private $Reference;
	
	/**
	 * @var DateTime
	 */
	private $Date;
	
	/**
	 * @var DateTime
	 */
	private $ValueDate;
	
	/**
	 * @var DateTime
	 */
	private $AccountingDate;
	
	/**
	 * @var string
	 */
	private $Currency;
	
	/**
	 * @var float
	 */
	private $Amount;
	
	/**
	 * @var string
	 */
	private $OriginalCurrency;
	
	/**
	 * @var float
	 */
	private $OriginalAmount;
	
	/**
	 * @var float
	 */
	private $ExchangeRate;
	
	/**
	 * @var string
	 */
	private $Type;
	
	/**
	 * @var string
	 */
	private $Description;
	
	/**
	 * @var string
	 */
	private $Reason;
	
	/**
	 * @var string
	 */
	private $Status;
	
	
	
	/**
	 * Hydrates specific data
	 * @param stdClass $data
	 */
	protected function hydrateData( stdClass $data ) {
		/* Unaccounted: there is no "uniqueId" */
		if( isset( $data->uniqueId ) ) {
			$this->UniqueId = (string)$data->uniqueId;
		}
		
		$this->Reference = (string)$data->reference;
		
		/* Unaccounted: there is no "date" */
		if( isset( $data->date ) ) {
			$this->Date = new DateTime( $data->date );
		}
		
		/* Unaccounted: there is no "accountingDate" */
		if( isset( $data->accountingDate ) ) {
			$this->AccountingDate = new DateTime( $data->accountingDate );
		}
		
		if( isset( $data->valueDate ) and !empty( $data->valueDate ) ) {
			$this->ValueDate = new DateTime( $data->valueDate );
			
		} else {
			$this->ValueDate = new DateTime();
		}
		
		$this->Currency = (string)$data->currency;
		$this->Amount = (float)$data->amount;
		
		$this->OriginalCurrency = (string)$data->originalCurrency;
		$this->OriginalAmount = (float)$data->originalAmount;
		$this->ExchangeRate = (float)$data->exchangeRate;
		
		$this->Type = (string)$data->type;
		$this->Description = (string)$data->description;
		
		/* Unaccounted: there is no "additionalInfo" */
		if( isset( $data->additionalInfo ) ) {
			$this->Reason = (string)$data->additionalInfo;
		}
		
		$this->Status = (string)$data->status;
	}
	
	
	/**
	 * @return string
	 */
	public function getUniqueId(): ?string {
		return $this->UniqueId;
	}
	
	
	/**
	 * @return string
	 */
	public function getReference(): ?string {
		return $this->Reference;
	}
	
	
	/**
	 * @return DateTime
	 */
	public function getDate(): ?DateTime {
		return $this->Date;
	}
	
	
	/**
	 * @return DateTime
	 */
	public function getValueDate(): ?DateTime {
		return $this->ValueDate;
	}
	
	
	/**
	 * @return DateTime
	 */
	public function getAccountingDate(): ?DateTime {
		return $this->AccountingDate;
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
	public function getAmount(): ?float {
		return $this->Amount;
	}
	
	
	/**
	 * @return string
	 */
	public function getOriginalCurrency(): ?string {
		return $this->OriginalCurrency;
	}
	
	
	/**
	 * @return float
	 */
	public function getOriginalAmount(): ?float {
		return $this->OriginalAmount;
	}
	
	
	/**
	 * @return float
	 */
	public function getExchangeRate(): ?float {
		return $this->ExchangeRate;
	}
	
	
	/**
	 * @return string
	 */
	public function getType(): ?string {
		return $this->Type;
	}
	
	
	/**
	 * @return string
	 */
	public function getDescription(): ?string {
		return $this->Description;
	}
	
	
	/**
	 * @return string
	 */
	public function getReason(): ?string {
		return $this->Reason;
	}
	
	
	/**
	 * @return string
	 */
	public function getStatus(): ?string {
		return $this->Status;
	}
}
