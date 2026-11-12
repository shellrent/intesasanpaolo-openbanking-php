<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Http;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Client\NetworkExceptionInterface;
use ReflectionMethod;
use SensitiveParameter;
use Shellrent\OpenBanking\Config\ClientConfig;
use Shellrent\OpenBanking\Config\Environment;
use Shellrent\OpenBanking\Exception\TransportException;
use Shellrent\OpenBanking\Http\Sender;
use Shellrent\OpenBanking\Tests\Support\MockHttp;
use Shellrent\OpenBanking\Tests\Unit\TestCase;

#[CoversClass(Sender::class)]
final class SenderTest extends TestCase
{
    #[Test]
    public function it_applies_the_certificate_timeouts_and_disables_http_errors(): void
    {
        $http = new MockHttp(new Response(500));
        $config = new ClientConfig('client-id', 'client-secret', self::IBAN, $this->certificate(), Environment::Sandbox, 5.0, 30.0);

        $response = (new Sender($config, $http->client))->send('GET', 'https://example.test/', headers: ['X-Test' => 'yes']);

        self::assertSame(500, $response->getStatusCode());

        $options = $http->options(0);
        self::assertFalse($options['http_errors'] ?? null);
        self::assertSame(5.0, $options['connect_timeout'] ?? null);
        self::assertSame(30.0, $options['timeout'] ?? null);
        self::assertSame($config->certificate->toGuzzleOptions()['cert'], $options['cert'] ?? null);
        self::assertSame($config->certificate->toGuzzleOptions()['ssl_key'] ?? null, $options['ssl_key'] ?? null);
        self::assertSame('yes', $http->request(0)->getHeaderLine('X-Test'));
    }

    #[Test]
    public function it_never_follows_redirects_and_hides_credentials_from_stack_traces(): void
    {
        $http = new MockHttp(new Response(200));

        (new Sender($this->config(), $http->client))->send('GET', 'https://example.test/');

        self::assertFalse($http->options(0)['allow_redirects'] ?? null);

        $sensitive = [];

        foreach ((new ReflectionMethod(Sender::class, 'send'))->getParameters() as $parameter) {
            if ($parameter->getAttributes(SensitiveParameter::class) !== []) {
                $sensitive[] = $parameter->getName();
            }
        }

        self::assertSame(['headers', 'json', 'formParams', 'body'], $sensitive);
    }

    #[Test]
    public function it_converts_network_errors_into_transport_exceptions(): void
    {
        $connectError = new ConnectException('Connection refused', new Request('POST', 'https://example.test/payments'));
        $http = new MockHttp($connectError);

        try {
            (new Sender($this->config(), $http->client))->send('POST', 'https://example.test/payments');
            self::fail('TransportException was not thrown');
        } catch (TransportException $exception) {
            self::assertSame('POST', $exception->method);
            self::assertSame('https://example.test/payments', $exception->uri);
            self::assertStringStartsWith('Network error', $exception->getMessage());
            self::assertInstanceOf(NetworkExceptionInterface::class, $exception->getPrevious());
        }
    }

    /**
     * Q-48: OpenSSL 3 cannot read the legacy encryption of the SmartHub PKCS#12 files.
     */
    #[Test]
    public function it_explains_an_unreadable_pkcs12_certificate(): void
    {
        $request = new Request('POST', 'https://example.test/token');
        $http = new MockHttp(new ConnectException('cURL error 58: could not parse PKCS12 file', $request));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('legacy encryption that OpenSSL 3 cannot read');

        (new Sender($this->config(), $http->client))->send('POST', 'https://example.test/token');
    }

    #[Test]
    public function it_converts_other_transfer_errors_into_transport_exceptions(): void
    {
        $http = new MockHttp(new RequestException('Broken transfer', new Request('GET', 'https://example.test/')));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('HTTP transfer error');

        (new Sender($this->config(), $http->client))->send('GET', 'https://example.test/');
    }
}
