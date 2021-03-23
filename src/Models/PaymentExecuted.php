<?php

namespace Shellrent\OpenBanking\Models;

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
	 * Hydrates specific data
	 * @param stdClass $data
	 */
	protected function hydrateData( stdClass $data ) {
		parent::hydrateData( $data );
		
		$payload = $data->payload;
		
		$this->PaymentStatus = $payload->paymentStatus;
		$this->CustomerCro = $payload->customerCro;
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
}
