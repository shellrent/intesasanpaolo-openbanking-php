<?php

namespace Shellrent\OpenBanking\Models;

use stdClass;
use DateTime;

use Shellrent\OpenBanking\Models\GenericPayment;


abstract class GenericPaymentData extends GenericPayment {
	/**
	 * @var string
	 */
	protected $OrderId;
	
	/**
	 * @var string
	 */
	protected $PaymentId;
	
	/**
	 * @var string
	 */
	protected $Currency;
	
	/**
	 * @var string
	 */
	protected $UltimateDebtorName;
	
	/**
	 * @var string
	 */
	protected $UltimateCreditorName;
	
	/**
	 * @var \DateTime
	 */
	protected $ValueDate;
	
	
	
	/**
	 * Hydrates specific data
	 * @param stdClass $data
	 */
	protected function hydrateData( stdClass $data ) {
		$this->OrderId = $data->{'order-id'};
		$this->PaymentId = $data->{'payment-id'};
		
		$this->setDebtorName( $data->debtorName );
		$this->setDebtorIban( $data->debtorIBAN );
		$this->setCreditorName( $data->creditorName );
		$this->setCreditorIban( $data->creditorIBAN );
		
		if( isset( $data->paymentInformation ) and !empty( $data->paymentInformation ) ) {
			$this->setPaymentInformation( $data->paymentInformation );
		}
		
		$this->setAmount( $data->amount );
		
		if( isset( $data->currency ) and !empty( $data->currency ) ) {
			$this->Currency = $data->currency;
		}
		
		$this->UltimateDebtorName = $data->ultimateDebtorName;
		$this->UltimateCreditorName = $data->ultimateCreditorName;
		
		if( isset( $data->valueDate ) and !empty( $data->paymentInformation ) ) {
			$this->ValueDate = DateTime::createFromFormat( 'd/m/Y', $data->valueDate );
		}
		
		if( isset( $data->resubmitId ) ) {
			$this->setResubmitId( $data->resubmitId );
		}
		
		if( isset( $data->endToEndId ) and !empty( $data->endToEndId ) ) {
			$this->setEndToEndId( $data->endToEndId );
		}
	}
	
	
	
	/**
	 * @return string|null
	 */
	public function getOrderId(): ?string {
		return $this->OrderId;
	}
	
	
	/**
	 * @return string|null
	 */
	public function getPaymentId(): ?string {
		return $this->PaymentId;
	}
	
	
	/**
	 * @return string|null
	 */
	public function getCurrency(): ?string {
		return $this->Currency;
	}
	
	
	/**
	 * @return string|null
	 */
	public function getUltimateDebtorName(): ?string {
		return $this->UltimateDebtorName;
	}
	
	
	/**
	 * @return string|null
	 */
	public function getUltimateCreditorName(): ?string {
		return $this->UltimateCreditorName;
	}
	
	
	/**
	 * @return \DateTime
	 */
	public function getValueDate(): ?DateTime {
		return $this->ValueDate;
	}
}
