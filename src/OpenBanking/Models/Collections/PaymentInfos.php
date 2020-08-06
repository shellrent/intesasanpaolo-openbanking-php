<?php

namespace Shellrent\OpenBanking\Models\Collections;

use DateTime;
use stdClass;

use Shellrent\OpenBanking\Models\Collections\ModelsCollectionInterface;
use Shellrent\OpenBanking\Models\GenericModel;
use Shellrent\OpenBanking\Models\PaymentInfo;


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
	 * @var \DateTime
	 */
	private $InquiryFromDate;
	
	/**
	 * @var \DateTime
	 */
	private $InquiryToDate;
	
	/**
	 * @var \Shellrent\OpenBanking\Models\PaymentInfo[]
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
		
		$this->InquiryFromDate = new DateTime( $payload->inquiryFromDate );
		$this->InquiryToDate = new DateTime( $payload->inquiryToDate );
		
		$this->addPayments( $payload->payments );
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
	 * @return \DateTime
	 */
	public function getInquiryFromDate(): ?DateTime {
		return $this->InquiryFromDate;
	}
	
	
	/**
	 * @return \DateTime
	 */
	public function getInquiryToDate(): ?DateTime {
		return $this->InquiryToDate;
	}
	
	
	/**
	 * @return \Shellrent\OpenBanking\Models\PaymentInfo[]
	 */
	public function getPayments(): array {
		return $this->Payments;
	}
}
