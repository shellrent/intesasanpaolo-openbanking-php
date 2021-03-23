<?php

namespace Shellrent\OpenBanking;

use DateTime;
use DateInterval;
use Shellrent\OpenBanking\Models\PaymentInfo;
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
		$response = $this->HttpClient->request( 'POST', sprintf( '%s/auth/oauth/v2/token', $this->BaseUri ), [
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
				RequestOptions::DEBUG => 2,
				
				
				RequestOptions::HEADERS => [
					'apikey' => $this->ClientId,
					'Authorization' => sprintf( 'Bearer %s', $this->Oauth2Bearer ),
				],
				RequestOptions::QUERY => $queryParameters,
			];
			
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
		var_dump((string)$response->getBody());
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
		
		$paymentSimulationResponse = $this->request( 'POST', sprintf( '%s/payments/sct/instant/simulation', $this->getApiBaseUri() ), [], $data );
		
		return new PaymentSimulated( $paymentSimulationResponse );
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
		var_dump( $data );
		var_dump( json_encode( $data ) );
		$paymentExecutedResponse = $this->request( 'POST', sprintf( '%s/payments/sct/instant', $this->getApiBaseUri() ), [], $data );
		
		return new PaymentExecuted( $paymentExecutedResponse );
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
		
		print_r( $paymentStatusResponse );
		
		return new PaymentStatus( $paymentStatusResponse );
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
}
