<?php

namespace Shellrent\OpenBanking\Models;

use stdClass;

class PaymentSimulated extends GenericPaymentResult {
	/**
	 * [ ACCP accepted, RJCT rejected ]
	 * @var string
	 */
	private $ValidationStatus;
	
	/**
	 * Hydrates specific data
	 * @param stdClass $data
	 */
	protected function hydrateData( stdClass $data ) {
		parent::hydrateData( $data );
		
		$payload = $data->payload;
		
		$this->ValidationStatus = $payload->validationStatus;
	}
	
	/**
	 * @return string|null
	 */
	public function getValidationStatus(): ?string {
		return $this->ValidationStatus;
	}
}
