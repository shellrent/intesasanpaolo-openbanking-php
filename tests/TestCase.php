<?php

namespace Shellrent\OpenBanking\Tests;

use PHPUnit\Framework\TestCase as PHPUnit;
use Shellrent\OpenBanking\IntesaSanPaoloClient;
use Dotenv\Dotenv;
use Faker\Generator;
use Faker\Factory;

abstract class TestCase extends PHPUnit {
	/**
	 * Faker for data generation
	 * @var Generator|null
	 */
	private $Faker;
	
	/**
	 * Client api open banking
	 * @var IntesaSanPaoloClient|null
	 */
	private $Client;
		

	/**
	 * Create fake data generator
	 * @return Generator
	 */
	protected function faker(): Generator {
		if( is_null( $this->Faker ) ) {
			$this->Faker = Factory::create();
		}
		
		return $this->Faker;
	}
	
	
	/**
	 * Generate client api for test
	 * @return IntesaSanPaoloClient
	 */
	protected function getClient(): IntesaSanPaoloClient {
		if( is_null( $this->Client ) ) {
			$this->Client = new IntesaSanPaoloClient( getenv( 'CLIENT_ID' ), getenv( 'CLIENT_SECRET' ), getenv( 'IBAN' ), false );
		}
		
		return $this->Client;
	}
	
	
	/**
	 * Load environment for test
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		$env = Dotenv::createUnsafeMutable( __DIR__ . '/../env' );
		$env->load();
	}
}
