<?php

namespace Shellrent\OpenBanking\MutualAuthentication;

class Certificate {
	/**
	 * @var string
	 */
	private $Pkcs12CertificatePath;
	
	/**
	 * @var string
	 */
	private $PemCertificatePath;
	
	/**
	 * @var string
	 */
	private $PrivateKeyPath;
	
	/**
	 * @var string
	 */
	private $CertificatePassphrasePath;
	
	/**
	 * @var string
	 */
	private $PrivateKeyPassphrasePath;
	
	
	/**
	 * SSL Certificate path (PKCS#12-encoded format)
	 * @return string
	 */
	public function getPkcs12CertificatePath(): string {
		return $this->Pkcs12CertificatePath;
	}
	
	
	/**
	 * SSL Certificate path (PKCS#12-encoded format)
	 * @param string $Pkcs12CertificatePath
	 * @return Certificate
	 */
	public function setPkcs12CertificatePath( string $Pkcs12CertificatePath ): Certificate {
		$this->Pkcs12CertificatePath = $Pkcs12CertificatePath;
		
		return $this;
	}
	
	
	/**
	 * SSL Certificate path ("pem"/plain format)
	 * @return string
	 */
	public function getPemCertificatePath(): string {
		return $this->PemCertificatePath;
	}
	
	
	/**
	 * SSL Certificate path ("pem"/plain format)
	 * @param string $PemCertificatePath
	 * @return Certificate
	 */
	public function setPemCertificatePath( string $PemCertificatePath ): Certificate {
		$this->PemCertificatePath = $PemCertificatePath;
		
		return $this;
	}
	
	
	/**
	 * Private Key path (plain format)
	 * @return string
	 */
	public function getPrivateKeyPath(): string {
		return $this->PrivateKeyPath;
	}
	
	
	/**
	 * Private Key path (plain format)
	 * @param string $PrivateKeyPath
	 * @return Certificate
	 */
	public function setPrivateKeyPath( string $PrivateKeyPath ): Certificate {
		$this->PrivateKeyPath = $PrivateKeyPath;
		
		return $this;
	}
	
	
	/**
	 * Passphrase for the certificate
	 * @return string
	 */
	public function getCertificatePassphrasePath(): string {
		return $this->CertificatePassphrasePath;
	}
	
	
	/**
	 * Passphrase for the certificate
	 * @param string $CertificatePassphrasePath
	 * @return Certificate
	 */
	public function setCertificatePassphrasePath( string $CertificatePassphrasePath ): Certificate {
		$this->CertificatePassphrasePath = $CertificatePassphrasePath;
		
		return $this;
	}
	
	
	/**
	 * Passphrase for the private key
	 * @return string
	 */
	public function getPrivateKeyPassphrasePath(): string {
		return $this->PrivateKeyPassphrasePath;
	}
	
	
	/**
	 * Passphrase for the private key
	 * @param string $PrivateKeyPassphrasePath
	 * @return Certificate
	 */
	public function setPrivateKeyPassphrasePath( string $PrivateKeyPassphrasePath ): Certificate {
		$this->PrivateKeyPassphrasePath = $PrivateKeyPassphrasePath;
		
		return $this;
	}
}