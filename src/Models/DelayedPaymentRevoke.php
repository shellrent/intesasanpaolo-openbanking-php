<?php

namespace Shellrent\OpenBanking\Models;

use stdClass;

class DelayedPaymentRevoke extends GenericModel {
	/**
	 * @var string
	 */
	private $PaymentId;
	
	/**
	 * @var string
	 */
	private $CustomerCro;
	
	/**
	 * @var string
	 */
	private $Status;
	
	
	
	/**
	 * Hydrates specific data
	 * @param stdClass $data
	 */
	protected function hydrateData( stdClass $data ) {
		$this->PaymentId = (string)$data->payload->paymentId;
		$this->CustomerCro = (string)$data->payload->customerCRO;
		$this->Status = (string)$data->payload->status;
	}
	
	
	/**
	 * @return string
	 */
	public function getStatus(): ?string {
		return $this->Status;
	}
	
	
	/**
	 * @return string
	 */
	public function getPaymentId(): ?string {
		return $this->PaymentId;
	}
	
	
	/**
	 * @return string
	 */
	public function getCustomerCro(): ?string {
		return $this->CustomerCro;
	}
}
