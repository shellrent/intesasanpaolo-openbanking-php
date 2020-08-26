<?php

namespace Shellrent\OpenBanking\Models;

use stdClass;

use Shellrent\OpenBanking\Models\GenericPayment;


class PaymentExecution extends GenericPayment {
	/**
	 * @var string
	 */
	private $SiaCode;
	
	/**
	 * @var bool
	 */
	private $Resubmit = false;
	
	
	
	/**
	 * Hydrates specific data
	 * @param stdClass $data
	 */
	protected function hydrateData( stdClass $data ) {
		/* No hydrate in this model */
	}
	
	
	/**
	 * @return string|null
	 */
	public function getSiaCode(): ?string {
		return $this->SiaCode;
	}
	
	
	/**
	 * @return bool
	 */
	public function getResubmit(): bool {
		return $this->Resubmit;
	}
	
	
	/**
	 * @param string $SiaCode
	 * @return $this
	 */
	public function setSiaCode( string $SiaCode ): self {
		$this->SiaCode = $SiaCode;
		
		return $this;
	}
	
	
	/**
	 * @param bool $Resubmit
	 * @return $this
	 */
	public function setResubmit( bool $Resubmit ): self {
		$this->Resubmit = $Resubmit;
		
		return $this;
	}
}
