<?php

namespace Shellrent\OpenBanking\Models;

use DateTime;
use stdClass;

class PaymentExecution extends GenericPayment {
	/**
	 * @var string
	 */
	private $SiaCode;
	
	/**
	 * @var bool
	 */
	private $Resubmit = false;
	
	/**
	 * @var string
	 */
	private $Currency;
	
	/**
	 * @var DateTime
	 */
	private $RequestedExecutionDate;
	
	
	
	/**
	 * Hydrates specific data
	 * @param stdClass $data
	 */
	protected function hydrateData( stdClass $data ) {
		/* No hydrate in this model */
	}
	
	
	/**
	 * @return string|null
	 */
	public function getSiaCode(): ?string {
		return $this->SiaCode;
	}
	
	
	/**
	 * @return bool
	 */
	public function getResubmit(): bool {
		return $this->Resubmit;
	}
	
	
	/**
	 * @param string $SiaCode
	 * @return $this
	 */
	public function setSiaCode( string $SiaCode ): self {
		$this->SiaCode = $SiaCode;
		
		return $this;
	}
	
	
	/**
	 * @param bool $Resubmit
	 * @return $this
	 */
	public function setResubmit( bool $Resubmit ): self {
		$this->Resubmit = $Resubmit;
		
		return $this;
	}
	
	
	/**
	 * @return string
	 */
	public function getCurrency(): string {
		return $this->Currency;
	}
	
	
	/**
	 * @param string $Currency
	 *
	 * @return PaymentExecution
	 */
	public function setCurrency( string $Currency ): PaymentExecution {
		$this->Currency = $Currency;
		return $this;
	}
	
	
	/**
	 * @return DateTime
	 */
	public function getRequestedExecutionDate(): DateTime {
		return $this->RequestedExecutionDate;
	}
	
	
	/**
	 * @param DateTime $RequestedExecutionDate
	 *
	 * @return PaymentExecution
	 */
	public function setRequestedExecutionDate( DateTime $RequestedExecutionDate ): PaymentExecution {
		$this->RequestedExecutionDate = $RequestedExecutionDate;
		return $this;
	}
}
