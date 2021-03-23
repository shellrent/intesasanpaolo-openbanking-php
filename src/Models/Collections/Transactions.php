<?php

namespace Shellrent\OpenBanking\Models\Collections;

use stdClass;
use Shellrent\OpenBanking\Models\GenericModel;
use Shellrent\OpenBanking\Models\Transaction;


class Transactions extends GenericModel implements ModelsCollectionInterface {
	/**
	 * @var int
	 */
	private $TotalAccountedTransactions;
	
	/**
	 * @var int
	 */
	private $TotalUnaccountedTransactions = 0;
	
	/**
	 * @var string
	 */
	private $BankCode;
	
	/**
	 * @var string
	 */
	private $CustomerCode;
	
	/**
	 * @var string
	 */
	private $SIACode;
	
	/**
	 * @var string
	 */
	private $AccountType;
	
	/**
	 * @var string
	 */
	private $AccountCurrency;
	
	/**
	 * @var string
	 */
	private $InitialAccountingBalance;
	
	/**
	 * @var string
	 */
	private $FinalAccountingBalance;
	
	/**
	 * @var string
	 */
	private $LiquidBalance;
	
	/**
	 * @var float
	 */
	private $AvailableBalance;
	
	/**
	 * @var float
	 */
	private $CreditLine;
	
	/**
	 * @var Transaction[]
	 */
	private $UnaccountedTransactions = [];
	
	/**
	 * @var Transaction[]
	 */
	private $AccountedTransactions = [];
	
	
	
	/**
	 * Hydrates specific data
	 * @param stdClass $data
	 */
	protected function hydrateData( stdClass $data ) {
		$payload = $data->payload;
		
		$this->TotalAccountedTransactions = (int)$payload->numTotalAccountedTransactions;
		
		if( isset( $payload->numUnaccountedTransactions ) ) {
			$this->TotalUnaccountedTransactions = (int)$payload->numUnaccountedTransactions;
		}
		
		$this->BankCode = (string)$payload->bankCode;
		$this->CustomerCode = (string)$payload->customerCode;
		$this->SIACode = (string)$payload->SIACode;
		$this->AccountType = (string)$payload->accountType;
		$this->AccountCurrency = (string)$payload->accountCurrency;
		$this->InitialAccountingBalance = (float)$payload->initialAccountingBalance;
		$this->FinalAccountingBalance = (float)$payload->finalAccountingBalance;
		$this->LiquidBalance = (float)$payload->liquidBalance;
		$this->AvailableBalance = (float)$payload->availableBalance;
		$this->CreditLine = (float)$payload->creditLine;
		
		if( isset( $payload->unaccountedTransactions ) ) {
			$this->addUnaccountedTransactions( $payload->unaccountedTransactions );
		}
		
		$this->addAccountedTransactions( $payload->accountedTransactions );
	}
	
	
	/**
	 * @return self
	 */
	public function addUnaccountedTransactions( $transactions ): self {
		foreach( $transactions as $unaccountedTransaction ) {
			$this->UnaccountedTransactions[] = new Transaction( $unaccountedTransaction );
		}
		
		return $this;
	}
	
	
	/**
	 * @return self
	 */
	public function addAccountedTransactions( $transactions ): self {
		foreach( $transactions as $accountedTransaction ) {
			$this->AccountedTransactions[] = new Transaction( $accountedTransaction );
		}
		
		return $this;
	}
	
	
	/**
	 * @return string
	 */
	public function getAccountCurrency(): ?string {
		return $this->AccountCurrency;
	}
	
	
	/**
	 * @return float
	 */
	public function getAvailableBalance(): ?float {
		return $this->AvailableBalance;
	}
	
	
	/**
	 * @return float
	 */
	public function getCreditLine(): ?float {
		return $this->CreditLine;
	}
	
	
	/**
	 * @return int
	 */
	public function getTotalAccountedTransactions(): ?int {
		return $this->TotalAccountedTransactions;
	}
	
	
	/**
	 * @return int
	 */
	public function getTotalUnaccountedTransactions(): ?int {
		return $this->TotalUnaccountedTransactions;
	}
	
	
	/**
	 * @return string
	 */
	public function getBankCode(): ?string {
		return $this->BankCode;
	}
	
	
	/**
	 * @return string
	 */
	public function getCustomerCode(): ?string {
		return $this->CustomerCode;
	}
	
	
	/**
	 * @return string
	 */
	public function getSIACode(): ?string {
		return $this->SIACode;
	}
	
	
	/**
	 * @return string
	 */
	public function getAccountType(): ?string {
		return $this->AccountType;
	}
	
	
	/**
	 * @return string
	 * @noinspection PhpUnused
	 */
	public function getInitialAccountingBalance(): ?string {
		return $this->InitialAccountingBalance;
	}
	
	
	/**
	 * @return string
	 * @noinspection PhpUnused
	 */
	public function getFinalAccountingBalance(): ?string {
		return $this->FinalAccountingBalance;
	}
	
	
	/**
	 * @return string
	 */
	public function getLiquidBalance(): ?string {
		return $this->LiquidBalance;
	}
	
	
	/**
	 * @return Transaction[]
	 */
	public function getUnaccountedTransactions(): array {
		return $this->UnaccountedTransactions;
	}
	
	
	/**
	 * @return Transaction[]
	 */
	public function getAccountedTransactions(): array {
		return $this->AccountedTransactions;
	}
}
