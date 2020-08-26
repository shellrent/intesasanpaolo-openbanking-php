<?php

namespace Shellrent\OpenBanking\Exceptions;

use Exception;

use GuzzleHttp\Exception\ClientException;


class HttpException extends Exception {
	/**
	 * @param \GuzzleHttp\Exception\ClientException $exception
	 */
	public function __construct( ClientException $exception ) {
		$this->buildException( $exception );
		
		parent::__construct( $this->message, $this->code, $exception );
	}
	
	
	/**
	 * @param \GuzzleHttp\Exception\ClientException $exception
	 * @return void
	 */
	private function buildException( ClientException $exception ) {
		$this->message = $exception->getMessage();
		$this->code = $exception->getCode();
		
		if( !( $exception instanceof ClientException ) ) {
			return;
		}
		
		$errorBody = $exception->getResponse()->getBody();
		$error = json_decode( $errorBody );
		
		if( empty( $error ) or !is_object( $error ) or !isset( $error->errors ) or !is_array( $error->errors ) or empty( $error->errors ) ) {
			return;
		}
		
		$data = array_shift( $error->errors );
		
		if( isset( $data->status ) ) {
			$this->code = (int)$data->status;
		}
		
		$title = '';
		$description = '';
		
		if( isset( $data->title ) and isset( $data->detail ) ) {
			$title = $data->title;
			
		} elseif( isset( $data->type ) or isset( $data->code ) ) {
			$title = trim( sprintf( '%s %s', isset( $data->type ) ? $data->type : '', isset( $data->code ) ? $data->code : '' ) );
		}
		
		if( isset( $data->detail ) and isset( $data->detail->reason ) ) {
			$description = $data->detail->reason;
			
		} elseif( isset( $data->description ) ) {
			$description = $data->description;
		}
		
		$this->message = trim( sprintf( '%s: %s', $title, $description ) );
	}
}
