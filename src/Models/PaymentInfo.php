<?php

namespace Shellrent\OpenBanking\Models;

use stdClass;

class PaymentInfo extends GenericPaymentInfo {
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
		
		$this->RemittanceInformation = $data->remittanceInformation;
	}
	
	
	/**
	 * @return string|null
	 */
	public function getRemittanceInformation(): ?string {
		return $this->RemittanceInformation;
	}
}
