<?php

namespace Shellrent\OpenBanking\Models;

use stdClass;

abstract class GenericPaymentInfo extends GenericPaymentData {
	/**
	 * [ Completed, Canceled, Rejected, Returned ]
	 * @var string
	 */
	private $Status;
	
	
	/**
	 * Hydrates specific data
	 * @param stdClass $data
	 */
	protected function hydrateData( stdClass $data ) {
		parent::hydrateData( $data );
		
		$this->Status = $data->status;
	}
	
	
	/**
	 * @return string|null
	 */
	public function getStatus(): ?string {
		return $this->Status;
	}
}
