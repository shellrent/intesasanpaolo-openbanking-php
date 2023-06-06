<?php

namespace Shellrent\OpenBanking\Models\Collections;

use DateTime;
use stdClass;
use Shellrent\OpenBanking\Models\GenericModel;
use Shellrent\OpenBanking\Models\PaymentInfo;
use Throwable;

class PaymentInfos extends GenericModel implements ModelsCollectionInterface {
	/**
	 * @var int
	 */
	private $TotalPayments;
	
	/**
	 * @var int
	 */
	private $PageSize;
	
	/**
	 * @var DateTime
	 */
	private $InquiryFromDate;
	
	/**
	 * @var DateTime
	 */
	private $InquiryToDate;
	
	/**
	 * @var PaymentInfo[]
	 */
	private $Payments = [];
	
	
	
	/**
	 * Hydrates specific data
	 * @param stdClass $data
	 */
	protected function hydrateData( stdClass $data ) {
		$payload = $data->payload;
		
		$this->TotalPayments = (int)$payload->paymentsTotalCount;
		$this->PageSize = (int)$payload->pageSize;
		
		try {
			$this->InquiryFromDate = new DateTime( $payload->inquiryFromDate );
			
		} catch( Throwable $exception ) {
			$this->InquiryFromDate = DateTime::createFromFormat( 'd/m/Y', $payload->inquiryFromDate );
		}
		
		try {
			$this->InquiryToDate = new DateTime( $payload->inquiryToDate );
			
		} catch( Throwable $exception ) {
			$this->InquiryToDate = DateTime::createFromFormat( 'd/m/Y', $payload->inquiryToDate );
		}
		
		if( isset( $payload->payments ) ) {
			$this->addPayments( $payload->payments );
			
		} elseif( isset( $payload->paymentsResults ) ) {
			$this->addPayments( $payload->paymentsResults );
		}
	}
	
	
	/**
	 * @return self
	 */
	public function addPayments( $paymentInfos ): self {
		foreach( $paymentInfos as $paymentInfo ) {
			$this->Payments[] = new PaymentInfo( $paymentInfo );
		}
		
		return $this;
	}
	
	
	/**
	 * @return int
	 */
	public function getTotalPayments(): ?int {
		return $this->TotalPayments;
	}
	
	
	/**
	 * @return int
	 */
	public function getPageSize(): ?int {
		return $this->PageSize;
	}
	
	
	/**
	 * @return DateTime
	 */
	public function getInquiryFromDate(): ?DateTime {
		return $this->InquiryFromDate;
	}
	
	
	/**
	 * @return DateTime
	 */
	public function getInquiryToDate(): ?DateTime {
		return $this->InquiryToDate;
	}
	
	
	/**
	 * @return PaymentInfo[]
	 */
	public function getPayments(): array {
		return $this->Payments;
	}
}
