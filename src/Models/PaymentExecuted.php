<?php

namespace Shellrent\OpenBanking\Models;

use DateTime;
use stdClass;

use Shellrent\OpenBanking\Models\GenericPaymentData;


class PaymentExecuted extends GenericPaymentData {
	/**
	 * @var string
	 */
	private $CustomerCro;
	
	/**
	 * @var string
	 */
	private $TransactionStatusDescription;
	
	/**
	 * [ ACSC, PNDG, RJCT ]
	 * @var string
	 */
	private $PaymentStatus;
	
	/**
	 * @var \DateTime
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
		parent::hydrateData( $data );
		
		$this->Date = new DateTime( $data->date );
		$this->CategoryPurpose = $data->categoryPurpose;
		
		$this->DebtorBic = $data->debtorBic;
		$this->CreditorBic = $data->creditorBic;
		
		$this->PaymentStatus = $data->paymentStatus;
		$this->TransactionStatusDescription = $data->transactionStatusDescription;
		$this->CustomerCro = $data->customerCro;
	}
	
	
	/**
	 * @return string|null
	 */
	public function getCustomerCro(): ?string {
		return $this->CustomerCro;
	}
	
	
	/**
	 * @return string|null
	 */
	public function getTransactionStatusDescription(): ?string {
		return $this->TransactionStatusDescription;
	}
	
	
	/**
	 * [ ACSC, PNDG, RJCT ]
	 * @return string|null
	 */
	public function getPaymentStatus(): ?string {
		return $this->PaymentStatus;
	}
	
	
	/**
	 * @return \DateTime|null
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
