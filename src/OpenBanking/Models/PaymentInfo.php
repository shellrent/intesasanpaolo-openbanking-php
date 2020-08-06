<?php

namespace Shellrent\OpenBanking\Models;

use stdClass;

use Shellrent\OpenBanking\Models\GenericPaymentData;


class PaymentInfo extends GenericPaymentData {
	/**
	 * [ Completed, Canceled, Rejected, Returned ]
	 * @var string
	 */
	private $Status;
	
	/**
	 * @var string
	 */
	private $RemittanceInformation;
	
	
	
	/**
	 * Hydrates specific data
	 * @param stdClass $data
	 */
	protected function hydrateData( stdClass $data ) {
		parent::hydrateData( $data );
		
		$this->Status = $data->status;
		$this->RemittanceInformation = $data->remittanceInformation;
	}
	
	
	/**
	 * @return string|null
	 */
	public function getStatus(): ?string {
		return $this->Status;
	}
	
	
	/**
	 * @return string|null
	 */
	public function getRemittanceInformation(): ?string {
		return $this->RemittanceInformation;
	}
}
