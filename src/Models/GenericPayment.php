<?php

namespace Shellrent\OpenBanking\Models;

use Shellrent\OpenBanking\Models\GenericModel;


abstract class GenericPayment extends GenericModel {
	/**
	 * @var string
	 */
	private $DebtorName;
	
	/**
	 * @var string
	 */
	private $DebtorIban;
	
	/**
	 * @var string
	 */
	private $CreditorName;
	
	/**
	 * @var string
	 */
	private $CreditorIban;
	
	/**
	 * @var float
	 */
	private $Amount = 0.00;
	
	/**
	 * @var string
	 */
	private $PaymentInformation;
	
	/**
	 * @var string
	 */
	private $EndToEndId;
	
	/**
	 * @var string
	 */
	private $ResubmitId;
	
	
	
	/**
	 * @return string
	 */
	public function getDebtorName(): ?string {
		return $this->DebtorName;
	}
	
	
	/**
	 * @return string
	 */
	public function getDebtorIban(): ?string {
		return $this->DebtorIban;
	}
	
	
	/**
	 * @return string
	 */
	public function getCreditorName(): ?string {
		return $this->CreditorName;
	}
	
	
	/**
	 * @return string
	 */
	public function getCreditorIban(): ?string {
		return $this->CreditorIban;
	}
	
	
	/**
	 * @return float
	 */
	public function getAmount(): float {
		return $this->Amount;
	}
	
	
	/**
	 * @return string|null
	 */
	public function getPaymentInformation(): ?string {
		return $this->PaymentInformation;
	}
	
	
	/**
	 * @return string|null
	 */
	public function getEndToEndId(): ?string {
		return $this->EndToEndId;
	}
	
	
	/**
	 * @return string|null
	 */
	public function getResubmitId(): ?string {
		return $this->ResubmitId;
	}
	
	
	/**
	 * @param string $DebtorName
	 * @return $this
	 */
	public function setDebtorName( string $DebtorName ): self {
		$this->DebtorName = $DebtorName;
		
		return $this;
	}
	
	
	/**
	 * @param string $DebtorIban
	 * @return $this
	 */
	public function setDebtorIban( string $DebtorIban ): self {
		$this->DebtorIban = $DebtorIban;
		
		return $this;
	}
	
	
	/**
	 * @param string $CreditorName
	 * @return $this
	 */
	public function setCreditorName( string $CreditorName ): self {
		$this->CreditorName = $CreditorName;
		
		return $this;
	}
	
	
	/**
	 * @param string $CreditorIban
	 * @return $this
	 */
	public function setCreditorIban( string $CreditorIban ): self {
		$this->CreditorIban = $CreditorIban;
		
		return $this;
	}
	
	
	/**
	 * @param float $Amount
	 * @return $this
	 */
	public function setAmount( float $Amount ): self {
		$this->Amount = $Amount;
		
		return $this;
	}
	
	
	/**
	 * @param string $PaymentInformation
	 * @return $this
	 */
	public function setPaymentInformation( string $PaymentInformation ): self {
		$this->PaymentInformation = $PaymentInformation;
		
		return $this;
	}
	
	
	/**
	 * @param string $EndToEndId
	 * @return $this
	 */
	public function setEndToEndId( string $EndToEndId ): self {
		$this->EndToEndId = $EndToEndId;
		
		return $this;
	}
	
	
	/**
	 * @param string $ResubmitId
	 * @return $this
	 */
	public function setResubmitId( string $ResubmitId ): self {
		$this->ResubmitId = $ResubmitId;
		
		return $this;
	}
}
