<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Shellrent\OpenBanking\Config\ClientConfig;
use Shellrent\OpenBanking\Config\Environment;
use Shellrent\OpenBanking\Config\MutualTlsCertificate;

/**
 * Base class for the unit tests of the 3.0 code.
 */
abstract class TestCase extends PHPUnitTestCase
{
    protected const string IBAN = 'IT00X0000000000000000000001';

    /**
     * @var list<string>
     */
    private array $temporaryFiles = [];

    #[\Override]
    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->temporaryFiles = [];

        parent::tearDown();
    }

    /**
     * Creates an empty temporary file, deleted after the test: certificates are never read by the mock handler.
     */
    protected function temporaryFile(string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), 'isp-sdk-');
        self::assertIsString($path);

        $withExtension = $path.'.'.$extension;
        self::assertTrue(rename($path, $withExtension));

        $this->temporaryFiles[] = $withExtension;

        return $withExtension;
    }

    protected function certificate(): MutualTlsCertificate
    {
        return MutualTlsCertificate::pem($this->temporaryFile('pem'), $this->temporaryFile('key'));
    }

    protected function config(Environment $environment = Environment::Sandbox): ClientConfig
    {
        return new ClientConfig('client-id', 'client-secret', self::IBAN, $this->certificate(), $environment);
    }
}
