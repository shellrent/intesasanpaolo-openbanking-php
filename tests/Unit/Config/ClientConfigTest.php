<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Config\ClientConfig;
use Shellrent\OpenBanking\Config\Environment;
use Shellrent\OpenBanking\Exception\ConfigurationException;
use Shellrent\OpenBanking\Tests\Unit\TestCase;

#[CoversClass(ClientConfig::class)]
final class ClientConfigTest extends TestCase
{
    #[Test]
    public function it_defaults_to_the_sandbox_and_the_standard_timeouts(): void
    {
        $config = new ClientConfig('client-id', 'client-secret', self::IBAN, $this->certificate());

        self::assertSame(Environment::Sandbox, $config->environment);
        self::assertSame(ClientConfig::DEFAULT_CONNECT_TIMEOUT, $config->connectTimeout);
        self::assertSame(ClientConfig::DEFAULT_TIMEOUT, $config->timeout);
    }

    /**
     * Q-01, Q-02: the APIs live under /twa/{environment}/v1, the token endpoint has no environment.
     */
    #[Test]
    public function it_builds_the_endpoint_uris(): void
    {
        $live = $this->config(Environment::Live);
        $sandbox = $this->config(Environment::Sandbox);

        self::assertSame('https://external-api.intesasanpaolo.com/twa/live/v1', $live->apiBaseUri());
        self::assertSame('https://external-api.intesasanpaolo.com/twa/sandbox/v1', $sandbox->apiBaseUri());
        self::assertSame('https://external-api.intesasanpaolo.com/auth/oauth/v2/token', $live->tokenUri());
        self::assertSame('https://external-api.intesasanpaolo.com/auth/oauth/v2/token', $sandbox->tokenUri());
    }

    #[Test]
    public function it_normalizes_the_iban(): void
    {
        $config = new ClientConfig('client-id', 'client-secret', ' it00 x000 0000 0000 0000 0000 001 ', $this->certificate());

        self::assertSame(self::IBAN, $config->iban);
    }

    #[Test]
    public function it_builds_the_basic_authorization_header(): void
    {
        self::assertSame('Basic '.base64_encode('client-id:client-secret'), $this->config()->basicAuthorization());
    }

    #[Test]
    public function it_hides_the_client_secret_from_debug_output(): void
    {
        $dump = print_r($this->config(), true);

        self::assertStringNotContainsString('client-secret', $dump);
        self::assertStringContainsString('client-id', $dump);
    }

    /**
     * @param  array{string, string, string, float, float}  $arguments
     */
    #[Test]
    #[DataProvider('invalidArguments')]
    public function it_rejects_invalid_arguments(array $arguments, string $message): void
    {
        [$clientId, $clientSecret, $iban, $connectTimeout, $timeout] = $arguments;

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage($message);

        new ClientConfig($clientId, $clientSecret, $iban, $this->certificate(), Environment::Sandbox, $connectTimeout, $timeout);
    }

    /**
     * @return iterable<string, array{array{string, string, string, float, float}, string}>
     */
    public static function invalidArguments(): iterable
    {
        yield 'empty client ID' => [[' ', 'secret', self::IBAN, 10.0, 60.0], 'client ID'];
        yield 'empty client secret' => [['id', '', self::IBAN, 10.0, 60.0], 'client secret'];
        yield 'invalid IBAN' => [['id', 'secret', 'not-an-iban', 10.0, 60.0], 'IBAN'];
        yield 'zero connect timeout' => [['id', 'secret', self::IBAN, 0.0, 60.0], 'connectTimeout'];
        yield 'negative timeout' => [['id', 'secret', self::IBAN, 10.0, -1.0], 'timeout'];
        yield 'infinite timeout' => [['id', 'secret', self::IBAN, 10.0, INF], 'timeout'];
    }
}
