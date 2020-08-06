<?php

namespace Shellrent\OpenBanking;

use DateTime;
use DateInterval;
use stdClass;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Exception\ClientException;

use Shellrent\OpenBanking\Models\Balance;
use Shellrent\OpenBanking\Models\Collections\Transactions;
use Shellrent\OpenBanking\Models\Collections\PaymentInfos;
use Shellrent\OpenBanking\Models\PaymentExecution;
use Shellrent\OpenBanking\Models\PaymentExecuted;

use Shellrent\OpenBanking\Exceptions\Exception;
use Shellrent\OpenBanking\Exceptions\HttpException;


class IntesaSanPaoloClient {
	/**
	 * Client HTTP
	 * @var \GuzzleHttp\Client
	 */
	private $HttpClient;
	
	/**
	 * Environment
	 * @var bool
	 */
	private $Live = false;
	
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
	 * URI for oAuth2
	 * @var string
	 */
	private $Oauth2Url;
	
	/**
	 * oAuth2 Bearer
	 * @var string
	 */
	private $Oauth2Bearer;
	
	/**
	 * oAuth2 Bearer
	 * @var \DateTime
	 */
	private $Oauth2BearerExpiry;
	
	/**
	 * Base URI for API requests
	 * @var string
	 */
	private $ApiBaseUri;
	
	
	
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
		$this->ApiBaseUri = sprintf( '%s/%s/v1', $this->BaseUri, $this->Live ? 'live' : 'sandbox' );
		$this->Oauth2Url = sprintf( '%s/auth/oauth/v2/token', $this->BaseUri );
		
		$this->HttpClient = new Client();
	}
	
	
	/**
	 * Login: oAuth2 request
	 * @throws \Shellrent\OpenBanking\Exceptions\Exception
	 */
	private function login() {
		$response = $this->HttpClient->request( 'POST', $this->Oauth2Url, [
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
	 * @param string $method
	 * @param string $url
	 * @param array $queryParameters
	 * @param string $body
	 * @return \stdClass
	 */
	private function request( string $method, string $url, array $queryParameters = [], string $body = null ): ?stdClass {
		$now = new DateTime();
		
		try {
			if( empty( $this->Oauth2Bearer ) or ( $this->Oauth2BearerExpiry < $now ) ) {
				$this->login();
			}
			
			$response = $this->HttpClient->request( $method, $url, [
				RequestOptions::HEADERS => [
					'apikey' => $this->ClientId,
					'Authorization' => sprintf( 'Bearer %s', $this->Oauth2Bearer ),
				],
				RequestOptions::QUERY => $queryParameters,
				RequestOptions::BODY => $body,
			]);
			
		} catch( ClientException $ex ) {
			throw new HttpException( $ex );
		}
		
		return json_decode( $response->getBody() );
	}
	
	
	/**
	 * Build a Transactions collection
	 * @param string $url
	 * @param array $params
	 * @return \Shellrent\OpenBanking\Models\Collections\Transactions
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
	 * Get the current Balance
	 * @return \Shellrent\OpenBanking\Models\Balance
	 */
	public function getBalance(): Balance {
		$balanceResponse = $this->request( 'GET', sprintf( '%s/accounts/%s/balance', $this->ApiBaseUri, $this->Iban ) );
		
		return new Balance( $balanceResponse );
	}
	
	
	/**
	 * Get transactions for a specific date
	 * @param DateTime $date
	 * @return \Shellrent\OpenBanking\Models\Collections\Transactions
	 */
	public function getTransactions( DateTime $date ): Transactions {
		return $this->buildTransactions( sprintf( '%s/accounts/%s/transactions', $this->ApiBaseUri, $this->Iban ), [
			'date' => $date->format( 'Ymd' ),
		]);
	}
	
	
	/**
	 * Get transactions for today
	 * @param DateTime $date
	 * @return \Shellrent\OpenBanking\Models\Collections\Transactions
	 */
	public function getTodayTransactions(): Transactions {
		return $this->buildTransactions( sprintf( '%s/accounts/%s/transactions/today', $this->ApiBaseUri, $this->Iban ) );
	}
	
	
	/**
	 * Creates a new Instant Payment (SCT-Instant-Execution)
	 * @param \Shellrent\OpenBanking\Models\PaymentExecution $payment
	 * @return \Shellrent\OpenBanking\Models\PaymentExecuted
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
		];
		
		if( $payment->getResubmit() ) {
			$data['resubmit'] = true;
			$data['resubmitId'] = $payment->getResubmitId();
			
		} else {
			$data['resubmit'] = false;
			$data['resubmitId'] = '';
		}
		
		$paymentExecutedResponse = $this->request( 'POST', sprintf( '%s/payments/sct/instant', $this->ApiBaseUri ), [], json_encode( $data ) );
		
		return new PaymentExecuted( $paymentExecutedResponse );
	}
	
	
	/**
	 * Get a list of the payments (SCT Instant - Payments List API)
	 * @param DateTime $fromDate If null, defaults to "1 month ago"
	 * @param DateTime $toDate
	 * @return \Shellrent\OpenBanking\Models\Collections\PaymentInfos
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
		
		$url = sprintf( '%s/payments/sct/instant/%s/history', $this->ApiBaseUri, $this->Iban );
		
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
