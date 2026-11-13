<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Http;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Auth\TokenProvider;
use Shellrent\OpenBanking\Config\ClientConfig;
use Shellrent\OpenBanking\Config\Environment;
use Shellrent\OpenBanking\Exception\ApiError;
use Shellrent\OpenBanking\Exception\ApiException;
use Shellrent\OpenBanking\Exception\AuthenticationException;
use Shellrent\OpenBanking\Exception\UnexpectedResponseException;
use Shellrent\OpenBanking\Http\ApiResponse;
use Shellrent\OpenBanking\Http\Sender;
use Shellrent\OpenBanking\Http\Transport;
use Shellrent\OpenBanking\Support\JsonNumber;
use Shellrent\OpenBanking\Tests\Support\MockHttp;
use Shellrent\OpenBanking\Tests\Unit\TestCase;

#[CoversClass(Transport::class)]
#[CoversClass(ApiResponse::class)]
#[CoversClass(ApiException::class)]
#[CoversClass(ApiError::class)]
final class TransportTest extends TestCase
{
    /**
     * Q-01, Q-03: API calls go to /twa/{environment}/v1 with the Bearer token and the "apikey" header.
     */
    #[Test]
    public function it_sends_authenticated_requests_to_the_api_base_uri(): void
    {
        $http = new MockHttp(MockHttp::token('token-123'), MockHttp::envelope(['currency' => 'EUR']));

        $response = $this->transport($http, $this->config(Environment::Live))
            ->get('accounts/'.self::IBAN.'/balance', ['date' => '20260115']);

        self::assertSame('EUR', $response->requirePayload()->string('currency'));

        $request = $http->request(1);
        self::assertSame('GET', $request->getMethod());
        self::assertSame(
            'https://external-api.intesasanpaolo.com/twa/live/v1/accounts/'.self::IBAN.'/balance?date=20260115',
            (string) $request->getUri(),
        );
        self::assertSame('Bearer token-123', $request->getHeaderLine('Authorization'));
        self::assertSame('client-id', $request->getHeaderLine('apikey'));
        self::assertSame('', $request->getHeaderLine('Content-Type'));
    }

    /**
     * Q-04: some GET requests must carry "Content-Type: application/json"; the header applies to that call only.
     */
    #[Test]
    public function it_sends_additional_headers_only_on_the_request_that_asks_for_them(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::envelope([]), MockHttp::envelope([]));
        $transport = $this->transport($http);

        $transport->get('payments/bonsct/'.self::IBAN.'/history', ['paymentId' => 'P1'], ['Content-Type' => 'application/json']);
        $transport->get('accounts/'.self::IBAN.'/balance');

        self::assertSame('application/json', $http->request(1)->getHeaderLine('Content-Type'));
        self::assertSame('', $http->request(2)->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function it_does_not_let_additional_headers_override_the_authentication(): void
    {
        $http = new MockHttp(MockHttp::token('token-123'), MockHttp::envelope([]));

        $this->transport($http)->get('accounts', [], ['Authorization' => 'Bearer forged', 'apikey' => 'forged']);

        self::assertSame('Bearer token-123', $http->request(1)->getHeaderLine('Authorization'));
        self::assertSame('client-id', $http->request(1)->getHeaderLine('apikey'));
    }

    #[Test]
    public function it_posts_a_json_body(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::envelope(['simulationId' => 'SIM-1']));

        $response = $this->transport($http)->post('payments/sct/instant/simulation', ['amount' => JsonNumber::amount('10.50'), 'endToEndId' => '']);

        self::assertSame('SIM-1', $response->requirePayload()->string('simulationId'));
        self::assertSame('POST', $http->request(1)->getMethod());
        self::assertSame('application/json', $http->request(1)->getHeaderLine('Content-Type'));
        self::assertSame('{"amount":10.5,"endToEndId":""}', (string) $http->request(1)->getBody());
    }

    #[Test]
    public function it_uses_absolute_urls_as_they_are(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::envelope([]));

        $this->transport($http)->get('https://external-api.intesasanpaolo.com/twa/sandbox/v1/accounts/x/transactions?offsetid=abc');

        self::assertSame(
            'https://external-api.intesasanpaolo.com/twa/sandbox/v1/accounts/x/transactions?offsetid=abc',
            (string) $http->request(1)->getUri(),
        );
    }

    /**
     * Q-06: the transaction ID is exposed on responses.
     */
    #[Test]
    public function it_exposes_the_transaction_id_and_the_headers(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::envelope([], ['X-ISP-Transaction-ID' => 'TX-1', 'vopMatchResult' => 'MTCH']));

        $response = $this->transport($http)->get('anything');

        self::assertSame('TX-1', $response->transactionId);
        self::assertSame('MTCH', $response->header('VOPMATCHRESULT'));
        self::assertNull($response->header('missing'));
    }

    #[Test]
    public function it_returns_a_null_payload_when_the_envelope_has_none(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::envelope(null));

        $response = $this->transport($http)->get('anything');

        self::assertNull($response->payload);

        $this->expectException(UnexpectedResponseException::class);
        $response->requirePayload();
    }

    /**
     * Q-05: the errors of the envelope become an ApiException.
     */
    #[Test]
    public function it_converts_http_errors_into_api_exceptions(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::json([
            'success' => false,
            'errors' => [['type' => 'INPUT_EXCEPTION', 'code' => 'E001', 'description' => 'Invalid IBAN']],
            'payload' => null,
        ], 400, ['X-ISP-Transaction-ID' => 'TX-2']));

        try {
            $this->transport($http)->get('anything');
            self::fail('ApiException was not thrown');
        } catch (ApiException $exception) {
            self::assertNotInstanceOf(AuthenticationException::class, $exception);
            self::assertSame(400, $exception->statusCode);
            self::assertSame(400, $exception->getCode());
            self::assertSame('TX-2', $exception->transactionId);
            self::assertEquals([new ApiError('INPUT_EXCEPTION', 'E001', 'Invalid IBAN')], $exception->errors);
            self::assertSame('Intesa Sanpaolo API error (HTTP 400): INPUT_EXCEPTION E001: Invalid IBAN', $exception->getMessage());
            self::assertNotNull($exception->responseBody);
        }
    }

    #[Test]
    public function it_converts_unsuccessful_envelopes_into_api_exceptions(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::json([
            'success' => false,
            'errors' => [['title' => 'Rejected', 'detail' => ['reason' => 'Cut-off passed']]],
            'payload' => null,
        ]));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('(HTTP 200): Rejected: Cut-off passed');

        $this->transport($http)->get('anything');
    }

    #[Test]
    public function it_returns_a_negative_outcome_only_when_asked(): void
    {
        $negative = ['success' => false, 'payload' => ['status' => 'KO']];
        $http = new MockHttp(MockHttp::token(), MockHttp::json($negative), MockHttp::json($negative));
        $transport = $this->transport($http);

        self::assertSame('KO', $transport->get('anything', acceptNegativeOutcome: true)->requirePayload()->string('status'));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('no error details in the response');

        $transport->get('anything');
    }

    #[Test]
    public function it_does_not_accept_a_negative_outcome_without_payload(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::json(['success' => false, 'errors' => [], 'payload' => null]));

        $this->expectException(ApiException::class);

        $this->transport($http)->get('anything', acceptNegativeOutcome: true);
    }

    #[Test]
    public function it_treats_success_false_as_a_string_as_a_failure(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::json(['success' => 'false', 'errors' => [['description' => 'Rejected']], 'payload' => ['isReachable' => true]]));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Rejected');

        $this->transport($http)->get('anything');
    }

    #[Test]
    public function it_does_not_send_credentials_outside_the_api_host(): void
    {
        $http = new MockHttp(MockHttp::token());

        try {
            $this->transport($http)->get('http://localhost:8081/twa/live/v1/payments/x');
            self::fail('UnexpectedResponseException was not thrown');
        } catch (UnexpectedResponseException $exception) {
            self::assertStringContainsString('outside the Intesa Sanpaolo API', $exception->getMessage());
        }

        self::assertSame(0, $http->count(), 'Not even the token was requested');
    }

    #[Test]
    public function it_keeps_large_integers_exact(): void
    {
        $http = new MockHttp(MockHttp::token(), new Response(200, [], '{"success": true, "payload": {"orderId": 123456789012345678901234}}'));

        self::assertSame('123456789012345678901234', $this->transport($http)->get('anything')->requirePayload()->string('orderId'));
    }

    #[Test]
    public function it_reports_non_json_error_responses(): void
    {
        $http = new MockHttp(MockHttp::token(), new Response(502, ['Content-Type' => 'text/html'], '<html>Bad Gateway</html>'));

        try {
            $this->transport($http)->get('anything');
            self::fail('ApiException was not thrown');
        } catch (ApiException $exception) {
            self::assertSame(502, $exception->statusCode);
            self::assertSame([], $exception->errors);
            self::assertSame('<html>Bad Gateway</html>', $exception->responseBody);
        }
    }

    #[Test]
    public function it_discards_the_token_after_an_http_401(): void
    {
        $http = new MockHttp(
            MockHttp::token('expired-on-server'),
            MockHttp::json(['success' => false, 'errors' => [['description' => 'Invalid token']], 'payload' => null], 401),
            MockHttp::token('fresh'),
            MockHttp::envelope([]),
        );
        $transport = $this->transport($http);

        try {
            $transport->get('anything');
            self::fail('AuthenticationException was not thrown');
        } catch (AuthenticationException $exception) {
            self::assertSame(401, $exception->statusCode);
        }

        $transport->get('anything');

        self::assertSame('Bearer fresh', $http->request(3)->getHeaderLine('Authorization'));
    }

    #[Test]
    public function it_rejects_a_success_response_that_is_not_json(): void
    {
        $http = new MockHttp(MockHttp::token(), new Response(200, [], 'not json'));

        try {
            $this->transport($http)->get('anything');
            self::fail('UnexpectedResponseException was not thrown');
        } catch (UnexpectedResponseException $exception) {
            self::assertSame('not json', $exception->responseBody);
        }
    }

    #[Test]
    public function it_treats_an_empty_payload_array_as_no_payload(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::json(['success' => true, 'errors' => null, 'payload' => []]));

        self::assertNull($this->transport($http)->get('anything')->payload);
    }

    #[Test]
    public function it_rejects_a_payload_that_is_not_an_object(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::json(['success' => true, 'payload' => [1, 2, 3]]));

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('payload is not a JSON object');

        $this->transport($http)->get('anything');
    }

    private function transport(MockHttp $http, ?ClientConfig $config = null): Transport
    {
        $config ??= $this->config();
        $sender = new Sender($config, $http->client);

        return new Transport($config, $sender, new TokenProvider($config, $sender));
    }
}
