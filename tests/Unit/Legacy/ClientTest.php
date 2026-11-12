<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Legacy;

use ArrayObject;
use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionProperty;
use Shellrent\OpenBanking\Exceptions\HttpException;
use Shellrent\OpenBanking\IntesaSanPaoloClient;
use Shellrent\OpenBanking\Models\PaymentExecution;

/**
 * Exercises the 2.x client through a real Guzzle client backed by a MockHandler.
 *
 * It checks that the legacy code works on every supported Guzzle major
 * (D-06) and pins the transport quirks that the 3.0 rewrite must preserve.
 */
#[CoversClass(IntesaSanPaoloClient::class)]
#[CoversClass(HttpException::class)]
final class ClientTest extends LegacyTestCase
{
    private const string IBAN = 'IT00X0000000000000000000001';

    /**
     * Requests recorded by Guzzle's history middleware.
     *
     * @var ArrayObject<int, array{request: RequestInterface, response: ResponseInterface|null, error: mixed, options: array<mixed>}>
     */
    private ArrayObject $history;

    protected function setUp(): void
    {
        $this->history = new ArrayObject;
    }

    /**
     * Q-01, Q-02, Q-03: token request with Basic auth and mTLS, then API calls on
     * the /twa/ base URL with the Bearer token and the "apikey" header.
     */
    #[Test]
    public function it_requests_a_token_and_the_balance_over_mutual_tls(): void
    {
        $client = $this->client(true, $this->tokenResponse(), $this->json([
            'success' => true,
            'errors' => null,
            'payload' => [
                'currency' => 'EUR',
                'availableBalance' => 1500.75,
                'accountingBalance' => 1450.25,
                'creditLine' => 0,
                'inquiryDate' => '20180131',
                'executionDate' => '2018-05-04T09:52:10.289+0200',
            ],
        ]));

        $balance = $client->getBalance(new DateTime('2018-01-31'));

        self::assertSame(1500.75, $balance->getAvailableBalance());
        self::assertCount(2, $this->history);

        $token = $this->recorded(0);
        self::assertSame('POST', $token['request']->getMethod());
        self::assertSame('https://external-api.intesasanpaolo.com/auth/oauth/v2/token', (string) $token['request']->getUri());
        self::assertSame('Basic '.base64_encode('client-id:client-secret'), $token['request']->getHeaderLine('Authorization'));
        self::assertSame('grant_type=client_credentials&scope=oob', (string) $token['request']->getBody());
        self::assertSame(__FILE__, $token['options']['cert'] ?? null);

        $call = $this->recorded(1);
        self::assertSame('GET', $call['request']->getMethod());
        self::assertSame(
            'https://external-api.intesasanpaolo.com/twa/live/v1/accounts/'.self::IBAN.'/balance?date=20180131',
            (string) $call['request']->getUri(),
        );
        self::assertSame('Bearer token-123', $call['request']->getHeaderLine('Authorization'));
        self::assertSame('client-id', $call['request']->getHeaderLine('apikey'));
        self::assertSame(__FILE__, $call['options']['cert'] ?? null);
        self::assertSame(__FILE__, $call['options']['ssl_key'] ?? null);
    }

    /**
     * Q-05: ISP errors are turned into an HttpException with "type code: description".
     */
    #[Test]
    public function it_converts_client_errors_into_http_exceptions(): void
    {
        $client = $this->client(true, $this->tokenResponse(), $this->json([
            'success' => false,
            'errors' => [['type' => 'INPUT_EXCEPTION', 'code' => 'E001', 'description' => 'Invalid IBAN']],
            'payload' => null,
        ], 400));

        try {
            $client->getBalance(new DateTime('2018-01-31'));
            self::fail('HttpException was not thrown');
        } catch (HttpException $exception) {
            self::assertSame('INPUT_EXCEPTION E001: Invalid IBAN', $exception->getMessage());
            self::assertSame(400, $exception->getCode());
        }
    }

    /**
     * Q-04: the SCT status and list GET requests carry "Content-Type: application/json".
     */
    #[Test]
    public function it_sends_a_json_content_type_on_the_sct_status_request(): void
    {
        $client = $this->client(true, $this->tokenResponse(), $this->json([
            'success' => true,
            'errors' => null,
            'payload' => ['paymentId' => 'PAY-0001', 'paymentStatus' => 'ACSC'],
        ]));

        $status = $client->getDelayedPaymentStatus('CRO-0001');

        self::assertSame('ACSC', $status->getStatus());
        self::assertSame(
            'https://external-api.intesasanpaolo.com/twa/live/v1/payments/bonsct/'.self::IBAN.'/history?customerCRO=CRO-0001',
            (string) $this->recorded(1)['request']->getUri(),
        );
        self::assertSame('application/json', $this->recorded(1)['request']->getHeaderLine('Content-Type'));
    }

    /**
     * Q-34: empty endToEndId and siaCode are sent as empty strings in sandbox and omitted in Live.
     *
     * @param  array<string, string>  $expectedOptionalFields
     */
    #[Test]
    #[DataProvider('environments')]
    public function it_handles_empty_optional_ids_depending_on_the_environment(bool $live, string $environment, array $expectedOptionalFields): void
    {
        $client = $this->client($live, $this->tokenResponse(), $this->json([
            'success' => true,
            'errors' => null,
            'payload' => [
                'transactionStatusDescription' => 'ACSC',
                'validationStatus' => 'ACSC',
                'simulationId' => 'SIM-0001',
                'amount' => 10,
                'currency' => 'EUR',
            ],
        ]));

        $simulation = $client->simulateInstantPayment(
            (new PaymentExecution)
                ->setDebtorName('Example Srl')
                ->setDebtorIban(self::IBAN)
                ->setCreditorName('Mario Rossi')
                ->setCreditorIban('IT00X0000000000000000000002')
                ->setAmount(10)
                ->setPaymentInformation('Test payment'),
        );

        self::assertSame('SIM-0001', $simulation->getSimulationId());

        $request = $this->recorded(1)['request'];
        self::assertSame(
            'https://external-api.intesasanpaolo.com/twa/'.$environment.'/v1/payments/sct/instant/simulation',
            (string) $request->getUri(),
        );

        $body = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame($expectedOptionalFields, array_intersect_key($body, ['endToEndId' => true, 'siaCode' => true]));
    }

    /**
     * @return iterable<string, array{bool, string, array<string, string>}>
     */
    public static function environments(): iterable
    {
        yield 'sandbox sends empty strings' => [false, 'sandbox', ['endToEndId' => '', 'siaCode' => '']];
        yield 'live omits the fields' => [true, 'live', []];
    }

    private function client(bool $live, Response ...$responses): IntesaSanPaoloClient
    {
        $history = $this->history;
        $stack = HandlerStack::create(new MockHandler(array_values($responses)));
        $stack->push(Middleware::history($history));

        $client = new IntesaSanPaoloClient('client-id', 'client-secret', self::IBAN, $live);
        $client->enableMutualAuthentication(__FILE__, null, __FILE__);

        (new ReflectionProperty(IntesaSanPaoloClient::class, 'HttpClient'))
            ->setValue($client, new Client(['handler' => $stack]));

        return $client;
    }

    /**
     * @return array{request: RequestInterface, response: ResponseInterface|null, error: mixed, options: array<mixed>}
     */
    private function recorded(int $index): array
    {
        $entry = $this->history[$index] ?? null;

        self::assertNotNull($entry, "No request was recorded at index {$index}");

        return $entry;
    }

    private function tokenResponse(): Response
    {
        return $this->json(['access_token' => 'token-123', 'token_type' => 'Bearer', 'expires_in' => 3600]);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function json(array $body, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
    }
}
