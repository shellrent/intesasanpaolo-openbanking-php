<?php

namespace Shellrent\OpenBanking\Exceptions;

use Exception as PhpException;
use Throwable;


class Exception extends PhpException {
	/**
	 * @param \GuzzleHttp\Exception\ClientException $exception
	 */
	public function __construct( string $message = '', int $code = 0, Throwable $previous = null ) {
		parent::__construct( $message, $code, $previous );
	}
}
