<?php

namespace Shellrent\OpenBanking\Models;

use DateTime;
use stdClass;

class PaymentExecuted extends GenericPaymentResult {
	/**
	 * @var string
	 */
	private $CustomerCro;
	
	/**
	 * [ ACSC, PNDG, RJCT ]
	 * @var string
	 */
	private $PaymentStatus;
	
	/**
	 * @var float
	 */
	private $Commissions;
	
	/**
	 * @var DateTime
	 */
	private $SettlemenDate;
	
	/**
	 * @var DateTime
	 */
	private $RevokeDate;
	
	/**
	 * @var string
	 */
	private $RevokeTime;
	
	
	
	/**
	 * Hydrates specific data
	 * @param stdClass $data
	 */
	protected function hydrateData( stdClass $data ) {
		parent::hydrateData( $data );
		
		$payload = $data->payload;
		
		$this->PaymentStatus = $payload->paymentStatus;
		$this->CustomerCro = $payload->customerCro;
		
		if( isset( $payload->commissioni ) ) {
			$this->Commissions = $payload->commissioni;
		}
		
		if( isset( $payload->settlemenDate ) ) {
			$this->SettlemenDate = DateTime::createFromFormat( 'd/m/Y', $payload->settlemenDate );
		}
		
		if( isset( $payload->revokeDate ) ) {
			$this->RevokeDate = DateTime::createFromFormat( 'd/m/Y', $payload->revokeDate );
		}
		
		if( isset( $payload->revokeTime ) ) {
			$this->RevokeTime = $payload->revokeTime;
		}
	}
	
	
	/**
	 * @return string|null
	 */
	public function getCustomerCro(): ?string {
		return $this->CustomerCro;
	}
	
	
	/**
	 * [ ACSC, PNDG, RJCT ]
	 * @return string|null
	 */
	public function getPaymentStatus(): ?string {
		return $this->PaymentStatus;
	}
	
	
	/**
	 * @return float
	 */
	public function getCommissions(): ?float {
		return $this->Commissions;
	}
	
	
	/**
	 * @return DateTime
	 */
	public function getSettlemenDate(): ?DateTime {
		return $this->SettlemenDate;
	}
	
	
	/**
	 * @return DateTime
	 */
	public function getRevokeDate(): ?DateTime {
		return $this->RevokeDate;
	}
	
	
	/**
	 * @return string
	 */
	public function getRevokeTime(): ?string {
		return $this->RevokeTime;
	}
}
