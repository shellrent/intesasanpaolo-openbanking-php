<?php

namespace Shellrent\OpenBanking;

use DateTime;
use DateInterval;
use Shellrent\OpenBanking\Models\DelayedPaymentRevoke;
use Shellrent\OpenBanking\Models\PaymentSimulated;
use Shellrent\OpenBanking\Models\PaymentStatus;
use stdClass;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Exception\ClientException;
use Shellrent\OpenBanking\Exceptions\Exception;
use Shellrent\OpenBanking\Exceptions\HttpException;
use Shellrent\OpenBanking\Models\Balance;
use Shellrent\OpenBanking\Models\Collections\Transactions;
use Shellrent\OpenBanking\Models\Collections\PaymentInfos;
use Shellrent\OpenBanking\Models\PaymentExecution;
use Shellrent\OpenBanking\Models\PaymentExecuted;


class IntesaSanPaoloClient {
	/**
	 * Client HTTP
	 *
	 * @var Client
	 */
	private $HttpClient;
	
	/**
	 * Environment
	 * @var bool
	 */
	private $Live;
	
	/**
	 * App Client ID
	 * @var string
	 */
	private $ClientId;
	
	/**
	 * App Client Secret
	 * @var string
	 */
	private $ClientSecret;
	
	/**
	 * IBAN to use in the requests
	 * @var string
	 */
	private $Iban;
	
	/**
	 * Base URI
	 * @var string
	 */
	private $BaseUri;
	
	/**
	 * oAuth2 Bearer
	 * @var string
	 */
	private $Oauth2Bearer;
	
	/**
	 * oAuth2 Bearer
	 *
	 * @var DateTime
	 */
	private $Oauth2BearerExpiry;
	
	/**
	 * Mutual Authentication Certificate paths
	 * @var string[]|string|null
	 */
	private $MutualAuthenticationCertificate;
	
	/**
	 * Mutual Authentication private key paths
	 * @var string[]|string|null
	 */
	private $MutualAuthenticationPrivateKey;
	
	/**
	 * Forces a request to send a "Content-Type" header: "Content-Type: application/json"
	 * @var bool
	 */
	private $ForceContentJson = false;
	
	
	
	/**
	 * @param string $clientId
	 * @param string $clientSecret
	 * @param string $iban
	 * @param bool $live
	 */
	public function __construct( string $clientId, string $clientSecret, string $iban, bool $live = false ) {
		$this->Live = $live;
		
		$this->ClientId = $clientId;
		$this->ClientSecret = $clientSecret;
		$this->Iban = $iban;
		
		$this->BaseUri = 'https://external-api.intesasanpaolo.com';
		
		$this->HttpClient = new Client();
	}
	
	
	/**
	 * Builds the base URI for API requests
	 * @return string
	 */
	private function getApiBaseUri(): string {
		$live = $this->Live ? 'live' : 'sandbox';
		$mutualAuthentication = $this->MutualAuthenticationCertificate ? 'twa/' : '';
		
		return sprintf( '%s/%s%s/v1', $this->BaseUri, $mutualAuthentication, $live );
	}
	
	
	/**
	 * Login: oAuth2 request
	 *
	 * @throws Exception
	 */
	private function login() {
		$response = $this->request( 'POST', sprintf( '%s/auth/oauth/v2/token', $this->BaseUri ), [
			RequestOptions::HEADERS => [
				'Host' => 'external-api.intesasanpaolo.com',
				'Content-Type' => 'application/x-www-form-urlencoded',
				'Authorization' => sprintf( 'Basic %s', base64_encode( sprintf( '%s:%s', $this->ClientId, $this->ClientSecret ) ) ),
			],
			RequestOptions::BODY => http_build_query([
				'grant_type'	=> 'client_credentials',
				'scope'			=> 'oob',
			]),
		]);
		
		$result = json_decode( $response->getBody() );
		
		if( !isset( $result->access_token ) or empty( $result->access_token ) or !isset( $result->token_type ) or ( $result->token_type != 'Bearer' ) ) {
			throw new Exception( 'Oauth2 Bearer not found' );
		}
		
		$this->Oauth2Bearer = $result->access_token;
		$this->Oauth2BearerExpiry = ( new DateTime() )->add( new DateInterval( sprintf( 'PT%sS', $result->expires_in ) ) );
	}
	
	
	/**
	 * Sends an API request
	 *
	 * @param string $method
	 * @param string $url
	 * @param array $queryParameters
	 * @param array|object $jsonBody
	 * @param string $plainBody
	 *
	 * @return stdClass
	 */
	private function request( string $method, string $url, array $queryParameters = [], $jsonBody = null, string $plainBody = null ): ?stdClass {
		$now = new DateTime();
		
		try {
			if( empty( $this->Oauth2Bearer ) or ( $this->Oauth2BearerExpiry < $now ) ) {
				$this->login();
			}
			
			$requestParams = [
				RequestOptions::HEADERS => [
					'apikey' => $this->ClientId,
					'Authorization' => sprintf( 'Bearer %s', $this->Oauth2Bearer ),
				],
				RequestOptions::QUERY => $queryParameters,
			];
			
			if( $this->ForceContentJson ) {
				$this->ForceContentJson = false;
				
				$requestParams[RequestOptions::HEADERS]['Content-Type'] = 'application/json';
			}
			
			if( !empty( $jsonBody ) ) {
				$requestParams[RequestOptions::JSON] = $jsonBody;
				
			} elseif( !empty( $plainBody ) ) {
				$requestParams[RequestOptions::BODY] = $plainBody;
			}
			
			if( $this->MutualAuthenticationCertificate ) {
				$requestParams[RequestOptions::CERT] = $this->MutualAuthenticationCertificate;
				
				if( $this->MutualAuthenticationPrivateKey ) {
					$requestParams[RequestOptions::SSL_KEY] = $this->MutualAuthenticationPrivateKey;
				}
			}
			
			$response = $this->HttpClient->request( $method, $url, $requestParams );
			
		} catch( ClientException $ex ) {
			throw new HttpException( $ex );
		}
		
		return json_decode( $response->getBody() );
	}
	
	
	/**
	 * Build a Transactions collection
	 *
	 * @param string $url
	 * @param array $params
	 *
	 * @return Transactions
	 */
	private function buildTransactions( string $url, array $params = [] ): Transactions {
		$transactions = null;
		
		while( $url ) {
			$transactionsResponse = $this->request( 'GET', $url, $params );
			
			if( isset( $transactionsResponse->payload ) and isset( $transactionsResponse->payload->nextPage ) and !empty( $transactionsResponse->payload->nextPage ) ) {
				$uri = new Uri( $transactionsResponse->payload->nextPage );
				parse_str( $uri->getQuery(), $params );
				
				$url = $transactionsResponse->payload->nextPage;
				
				/* URL next page on Sandbox is unreachable - it's a known bug */
				if( !$this->Live ) {
					$url = str_replace( 'https://external-api.syssede.systest.sanpaoloimi.com', $this->BaseUri, $transactionsResponse->payload->nextPage );
				}
				
			} else {
				$url = null;
			}
			
			if( !$transactions ) {
				$transactions = new Transactions( $transactionsResponse );
				
			} else {
				if( isset( $transactionsResponse->payload->unaccountedTransactions ) ) {
					$transactions->addUnaccountedTransactions( $transactionsResponse->payload->unaccountedTransactions );
				}
				
				$transactions->addAccountedTransactions( $transactionsResponse->payload->accountedTransactions );
			}
		}
		
		return $transactions;
	}
	
	
	/**
	 * Enables the use of mutual authentication with an SSL Certificate
	 * @param string $certificatePath Path to the SSL Certificate; must be in PEM format or PKCS#12-encoded format if Guzzle is 7.3.0+
	 * @param string|null $certificatePassphrase String containing the passphrase for the Certificate
	 * @param string|null $privateKeyPath Path to the Private Key file
	 * @param string|null $privateKeyPassphrase String containing the passphrase for the Private Key
	 * @return $this
	 */
	public function enableMutualAuthentication( string $certificatePath, ?string $certificatePassphrase = null, string $privateKeyPath = null, string $privateKeyPassphrase = null ): self {
		if( !empty( $certificatePassphrase ) ) {
			$this->MutualAuthenticationCertificate = [
				$certificatePath,
				$certificatePassphrase,
			];
			
		} else {
			$this->MutualAuthenticationCertificate = $certificatePath;
		}
		
		if( !empty( $privateKeyPath ) ) {
			if( !empty( $privateKeyPassphrase ) ) {
				$this->MutualAuthenticationPrivateKey = [
					$privateKeyPath,
					$privateKeyPassphrase,
				];
				
			} else {
				$this->MutualAuthenticationPrivateKey = $privateKeyPath;
			}
			
		} else {
			$this->MutualAuthenticationPrivateKey = null;
		}
		
		return $this;
	}
	
	
	/**
	 * Disables the use of mutual authentication with an SSL Certificate
	 * @return $this
	 */
	public function disableMutualAuthentication(): self {
		$this->MutualAuthenticationCertificate = null;
		$this->MutualAuthenticationPrivateKey = null;
		
		return $this;
	}
	
	
	/**
	 * Get the current Balance
	 *
	 * @param DateTime $date
	 *
	 * @throws HttpException
	 * @return Balance
	 */
	public function getBalance( DateTime $date ): Balance {
		$params = [];
		
		if( $date ) {
			$params['date'] = $date->format( 'Ymd' );
		}
		
		$balanceResponse = $this->request( 'GET', sprintf( '%s/accounts/%s/balance', $this->getApiBaseUri(), $this->Iban ), $params );
		
		return new Balance( $balanceResponse );
	}
	
	
	/**
	 * Get transactions for a specific date
	 *
	 * @param DateTime $date
	 *
	 * @return Transactions
	 */
	public function getTransactions( DateTime $date ): Transactions {
		return $this->buildTransactions( sprintf( '%s/accounts/%s/transactions', $this->getApiBaseUri(), $this->Iban ), [
			'date' => $date->format( 'Ymd' ),
		]);
	}
	
	
	/**
	 * Get transactions for today
	 *
	 * @return Transactions
	 */
	public function getTodayTransactions(): Transactions {
		return $this->buildTransactions( sprintf( '%s/accounts/%s/transactions/today', $this->getApiBaseUri(), $this->Iban ) );
	}
	
	
	/**
	 * Simulates an Instant Payment (SCT-Instant-Simulation)
	 *
	 * @param PaymentExecution $payment
	 *
	 * @return PaymentSimulated
	 */
	public function simulateDelayedPayment( PaymentExecution $payment ): PaymentSimulated {
		$data = [
			'debtorName'			=> $payment->getDebtorName(),
			'debtorIban'			=> $payment->getDebtorIban(),
			'creditorName'			=> $payment->getCreditorName(),
			'creditorIban'			=> $payment->getCreditorIban(),
			'amount'				=> $payment->getAmount(),
			'currency'				=> $payment->getCurrency(),
			'paymentInformation'	=> $payment->getPaymentInformation(),
			'requestedExecutionDate'=> $payment->getRequestedExecutionDate()->format( 'd/m/Y' ),
			'endToEndId'			=> empty( $payment->getEndToEndId() ) ? '' : $payment->getEndToEndId(),
			'categoryPurpose'		=> 'CASH',
		];
		
		/* Sandbox: endToEndId and siaCode must be present and empty; Live: if empty, they must be omitted */
		if( $this->Live ) {
			if( empty( $data['endToEndId'] ) ) {
				unset( $data['endToEndId'] );
			}
			
			if( empty( $data['siaCode'] ) ) {
				unset( $data['siaCode'] );
			}
		}
		
		$paymentSimulationResponse = $this->request( 'POST', sprintf( '%s/payments/bonsct/simulation', $this->getApiBaseUri() ), [], $data );
		
		return new PaymentSimulated( $paymentSimulationResponse );
	}
	
	
	/**
	 * Simulates an Instant Payment (SCT-Instant-Simulation)
	 *
	 * @param PaymentExecution $payment
	 *
	 * @return PaymentSimulated
	 */
	public function simulateInstantPayment( PaymentExecution $payment ): PaymentSimulated {
		$data = [
			'debtorName'			=> $payment->getDebtorName(),
			'debtorIBAN'			=> $payment->getDebtorIban(),
			'creditorName'			=> $payment->getCreditorName(),
			'creditorIBAN'			=> $payment->getCreditorIban(),
			'amount'				=> $payment->getAmount(),
			'paymentInformation'	=> $payment->getPaymentInformation(),
			'endToEndId'			=> empty( $payment->getEndToEndId() ) ? '' : $payment->getEndToEndId(),
			'siaCode'				=> empty( $payment->getSiaCode() ) ? '' : $payment->getSiaCode(),
		];
		
		/* Sandbox: endToEndId and siaCode must be present and empty; Live: if empty, they must be omitted */
		if( $this->Live ) {
			if( empty( $data['endToEndId'] ) ) {
				unset( $data['endToEndId'] );
			}
			
			if( empty( $data['siaCode'] ) ) {
				unset( $data['siaCode'] );
			}
		}
		
		$paymentSimulationResponse = $this->request( 'POST', sprintf( '%s/payments/sct/instant/simulation', $this->getApiBaseUri() ), [], $data );
		
		return new PaymentSimulated( $paymentSimulationResponse );
	}
	
	
	/**
	 * Creates a new "delayed" Payment (SCT-Execution) (bonifico ordinario)
	 *
	 * @param PaymentExecution $payment
	 *
	 * @return PaymentExecuted
	 */
	public function createDelayedPayment( PaymentExecution $payment ): PaymentExecuted {
		$data = [
			'simulationId'			=> $payment->getSimulationId(),
			'debtorName'			=> $payment->getDebtorName(),
			'debtorIban'			=> $payment->getDebtorIban(),
			'creditorName'			=> $payment->getCreditorName(),
			'creditorIban'			=> $payment->getCreditorIban(),
			'amount'				=> $payment->getAmount(),
			'currency'				=> $payment->getCurrency(),
			'paymentInformation'	=> $payment->getPaymentInformation(),
			'requestedExecutionDate'=> $payment->getRequestedExecutionDate()->format( 'd/m/Y' ),
			'endToEndId'			=> $payment->getEndToEndId(),
			'categoryPurpose'		=> 'CASH',
		];
		
		/* Sandbox: endToEndId and siaCode must be present and empty; Live: if empty, they must be omitted */
		if( $this->Live ) {
			if( empty( $data['endToEndId'] ) ) {
				unset( $data['endToEndId'] );
			}
		}
		
		$paymentExecutedResponse = $this->request( 'POST', sprintf( '%s/payments/bonsct', $this->getApiBaseUri() ), [], $data );
		
		return new PaymentExecuted( $paymentExecutedResponse );
	}
	
	
	/**
	 * Creates a new Instant Payment (SCT-Instant-Execution)
	 *
	 * @param PaymentExecution $payment
	 *
	 * @return PaymentExecuted
	 */
	public function createInstantPayment( PaymentExecution $payment ): PaymentExecuted {
		$data = [
			'debtorName'			=> $payment->getDebtorName(),
			'debtorIBAN'			=> $payment->getDebtorIban(),
			'creditorName'			=> $payment->getCreditorName(),
			'creditorIBAN'			=> $payment->getCreditorIban(),
			'amount'				=> $payment->getAmount(),
			'paymentInformation'	=> $payment->getPaymentInformation(),
			'endToEndId'			=> $payment->getEndToEndId(),
			'siaCode'				=> $payment->getSiaCode(),
			'simulationId'			=> $payment->getSimulationId(),
		];
		
		if( $payment->getResubmit() ) {
			$data['resubmit'] = true;
			$data['resubmitId'] = $payment->getResubmitId();
		}
		
		if( is_null( $data['siaCode'] ) ) {
			$data['siaCode'] = '';
		}
		
		/* Sandbox: endToEndId and siaCode must be present and empty; Live: if empty, they must be omitted */
		if( $this->Live ) {
			if( empty( $data['endToEndId'] ) ) {
				unset( $data['endToEndId'] );
			}
			
			if( empty( $data['siaCode'] ) ) {
				unset( $data['siaCode'] );
			}
		}
		
		$paymentExecutedResponse = $this->request( 'POST', sprintf( '%s/payments/sct/instant', $this->getApiBaseUri() ), [], $data );
		
		return new PaymentExecuted( $paymentExecutedResponse );
	}
	
	
	/**
	 * Retrieve the SCT payment's information about a specified IBAN and paymentId or customerCRO (BONSCT - Payment Status API)
	 * @param string $customerCro
	 * @param string|null $paymentId
	 * @return PaymentStatus
	 */
	public function getDelayedPaymentStatus( string $customerCro = null, string $paymentId = null ): PaymentStatus {
		$params = [];
		
		$paramsOk = false;
		
		if( $customerCro ) {
			$params['customerCRO'] = $customerCro;
			$paramsOk = true;
		}
		
		if( $paymentId ) {
			$params['paymentId'] = $paymentId;
			$paramsOk = true;
		}
		
		if( !$paramsOk ) {
			throw new Exception( 'A customer CRO or a Payment ID must be specified to retreive payment status' );
		}
		
		$this->ForceContentJson = true;
		$paymentStatusResponse = $this->request( 'GET', sprintf( '%s/payments/bonsct/%s/history', $this->getApiBaseUri(), $this->Iban ), $params );
		
		return new PaymentStatus( $paymentStatusResponse );
	}
	
	
	/**
	 * Get the status of a payment (SCT Instant - Payment Status API)
	 * @param string $orderId
	 * @return PaymentStatus
	 */
	public function getPaymentStatus( string $orderId, string $paymentId = null ): PaymentStatus {
		$params = [];
		
		if( $paymentId ) {
			$params['paymentId'] = $paymentId;
		}
		
		$paymentStatusResponse = $this->request( 'GET', sprintf( '%s/payments/sct/instant/%s/history/%s', $this->getApiBaseUri(), $this->Iban, $orderId ), $params );
		
		return new PaymentStatus( $paymentStatusResponse );
	}
	
	
	/**
	 * Retrieve the payments list for a specified IBAN, range of dates and payments direction. (BON SCT - Payments List API)
	 *
	 * @param DateTime $fromDate If null, defaults to "1 month ago"
	 * @param DateTime $toDate
	 *
	 * @return PaymentInfos
	 */
	public function getDelayedPaymentsList( DateTime $fromDate = null, DateTime $toDate = null ): PaymentInfos {
		if( is_null( $fromDate ) ) {
			$fromDate = new DateTime();
			$fromDate->modify( '-1 month' );
		}
		
		$params = [
			'fromDate' => $fromDate->format( 'Ymd' ),
			'offset' => 0,
			'limit' => 30,
			'paymentDirection' => 'O',
		];
		
		if( !is_null( $toDate ) ) {
			$params['toDate'] = $toDate->format( 'Ymd' );
		}
		
		$url = sprintf( '%s/payments/bonsct/%s/list', $this->getApiBaseUri(), $this->Iban );
		
		$payments = null;
		
		while( $url ) {
			$this->ForceContentJson = true;
			$paymentsResponse = $this->request( 'GET', $url, $params );
			
			if( isset( $paymentsResponse->payload ) and isset( $paymentsResponse->payload->links ) and isset( $paymentsResponse->payload->links->next ) and !empty( $paymentsResponse->payload->links->next ) ) {
				$uri = new Uri( $paymentsResponse->payload->links->next );
				parse_str( $uri->getQuery(), $params );
				
				if( !isset( $params['paymentDirection'] ) ) {
					$params['paymentDirection'] = 'O';
				}
				
				$url = $paymentsResponse->payload->links->next;
				
				/* URL next page on Sandbox is unreachable - it's a known bug */
				if( !$this->Live ) {
					$url = str_replace( 'http://localhost:8081', sprintf( '%s/sandbox', $this->BaseUri ), $url );
					$url = str_replace( '/IT59R0306901001100000002110/', sprintf( '/%s/', $this->Iban ), $url );
				}
				
			} else {
				$url = null;
			}
			
			if( empty( $paymentsResponse->payload->paymentsResults ) ) {
				$url = null;
			}
			
			if( !$payments ) {
				$payments = new PaymentInfos( $paymentsResponse );
				
			} else {
				$payments->addPayments( $paymentsResponse->payload->paymentsResults );
			}
		}
		
		return $payments;
	}
	
	
	/**
	 * Get a list of the payments (SCT Instant - Payments List API)
	 *
	 * @param DateTime $fromDate If null, defaults to "1 month ago"
	 * @param DateTime $toDate
	 *
	 * @return PaymentInfos
	 */
	public function getPaymentsList( DateTime $fromDate = null, DateTime $toDate = null ): PaymentInfos {
		if( is_null( $fromDate ) ) {
			$fromDate = new DateTime();
			$fromDate->modify( '-1 month' );
		}
		
		$params = [
			'fromDate' => $fromDate->format( 'Ymd' ),
			'offset' => 0,
			'limit' => 100,
			'paymentDirection' => 'O',
		];
		
		if( !is_null( $toDate ) ) {
			$params['toDate'] = $toDate->format( 'Ymd' );
		}
		
		$url = sprintf( '%s/payments/sct/instant/%s/history', $this->getApiBaseUri(), $this->Iban );
		
		$payments = null;
		
		while( $url ) {
			$paymentsResponse = $this->request( 'GET', $url, $params );
			
			if( isset( $paymentsResponse->payload ) and isset( $paymentsResponse->payload->links ) and isset( $paymentsResponse->payload->links->next ) and !empty( $paymentsResponse->payload->links->next ) ) {
				$uri = new Uri( $paymentsResponse->payload->links->next );
				parse_str( $uri->getQuery(), $params );
				
				if( !isset( $params['paymentDirection'] ) ) {
					$params['paymentDirection'] = 'O';
				}
				
				$url = $paymentsResponse->payload->links->next;
				
				/* URL next page on Sandbox is unreachable - it's a known bug */
				if( !$this->Live ) {
					$url = str_replace( 'http://localhost:8081', sprintf( '%s/sandbox', $this->BaseUri ), $url );
					$url = str_replace( '/IT59R0306901001100000002110/', sprintf( '/%s/', $this->Iban ), $url );
				}
				
			} else {
				$url = null;
			}
			
			if( empty( $paymentsResponse->payload->payments ) ) {
				$url = null;
			}
			
			if( !$payments ) {
				$payments = new PaymentInfos( $paymentsResponse );
				
			} else {
				$payments->addPayments( $paymentsResponse->payload->payments );
			}
		}
		
		return $payments;
	}
	
	
	/**
	 * Try to Revoke the SCT payment specified by IBAN and paymentId or customerCRO (BON SCT - Revoke API)
	 * @return DelayedPaymentRevoke
	 */
	public function revokeDelayedPayment( string $customerCro = null, string $paymentId = null ): DelayedPaymentRevoke {
		$params = [];
		
		$paramsOk = false;
		
		if( $customerCro ) {
			$params['customerCRO'] = $customerCro;
			$paramsOk = true;
		}
		
		if( $paymentId ) {
			$params['paymentId'] = $paymentId;
			$paramsOk = true;
		}
		
		if( !$paramsOk ) {
			throw new Exception( 'A customer CRO or a Payment ID must be specified to retreive payment status' );
		}
		
		$paymentExecutedResponse = $this->request( 'GET', sprintf( '%s/payments/bonsct/%s/revoke', $this->getApiBaseUri(), $this->Iban ), $params );
		
		return new DelayedPaymentRevoke( $paymentExecutedResponse );
	}
}
