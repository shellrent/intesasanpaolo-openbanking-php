<?php

namespace Shellrent\OpenBanking\Models;

use DateTime;
use stdClass;

abstract class GenericPaymentResult extends GenericPaymentData {
	/**
	 * @var string
	 */
	private $TransactionStatusDescription;
	
	/**
	 * @var DateTime
	 */
	private $Date;
	
	/**
	 * @var string
	 */
	private $CategoryPurpose;
	
	/**
	 * @var string
	 */
	private $DebtorBic;
	
	/**
	 * @var string
	 */
	private $CreditorBic;
	
	
	
	/**
	 * Hydrates specific data
	 * @param stdClass $data
	 */
	protected function hydrateData( stdClass $data ) {
		$payload = $data->payload;
		
		parent::hydrateData( $payload );
		
		if( isset( $payload->date ) ) {
			$this->Date = new DateTime( $payload->date );
		}
		
		if( isset( $payload->categoryPurpose ) ) {
			$this->CategoryPurpose = $payload->categoryPurpose;
		}
		
		if( isset( $payload->debtorBic ) ) {
			$this->DebtorBic = $payload->debtorBic;
		}
		
		if( isset( $payload->creditorBic ) ) {
			$this->CreditorBic = $payload->creditorBic;
		}
		
		$this->TransactionStatusDescription = $payload->transactionStatusDescription;
	}
	
	
	/**
	 * @return string|null
	 */
	public function getTransactionStatusDescription(): ?string {
		return $this->TransactionStatusDescription;
	}
	
	
	/**
	 * @return DateTime|null
	 */
	public function getDate(): ?DateTime {
		return $this->Date;
	}
	
	
	/**
	 * @return string|null
	 */
	public function getCategoryPurpose(): ?string {
		return $this->CategoryPurpose;
	}
	
	
	/**
	 * @return string|null
	 */
	public function getDebtorBic(): ?string {
		return $this->DebtorBic;
	}
	
	
	/**
	 * @return string|null
	 */
	public function getCreditorBic(): ?string {
		return $this->CreditorBic;
	}
}
