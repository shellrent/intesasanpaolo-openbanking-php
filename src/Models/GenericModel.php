<?php

namespace Shellrent\OpenBanking\Models;

use DateTime;
use stdClass;


abstract class GenericModel {
	/**
	 * @var \DateTime
	 */
	private $InquiryDate = null;
	
	/**
	 * @var \DateTime
	 */
	private $ExecutionDate = null;
	
	
	
	public final function __construct( stdClass $data = null ) {
		if( !is_null( $data ) ) {
			$this->hydrate( $data );
		}
	}
	
	
	/**
	 * @param \stdClass $data
	 */
	public final function hydrate( stdClass $data ) {
		if( isset( $data->payload ) ) {
			$payload = $data->payload;
			
			if( isset( $payload->inquiryDate ) ) {
				$this->InquiryDate = ( new DateTime( $payload->inquiryDate ) )->setTime( 0, 0, 0, 0 );
			}
			
			if( isset( $payload->executionDate ) ) {
				$this->ExecutionDate = new DateTime( $payload->executionDate );
			}
		}
		
		$this->hydrateData( $data );
	}
	
	
	/**
	 * @return \DateTime
	 */
	public final function getInquiryDate(): ?DateTime {
		return $this->InquiryDate;
	}
	
	
	/**
	 * @return \DateTime
	 */
	public final function getExecutionDate(): ?DateTime {
		return $this->ExecutionDate;
	}
	
	
	/**
	 * Hydrates specific fields
	 * @param stdClass $data
	 */
	protected abstract function hydrateData( stdClass $data );
}
