<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Auth\InMemoryTokenStore;
use Shellrent\OpenBanking\Auth\TokenProvider;
use Shellrent\OpenBanking\Auth\TokenStore;
use Shellrent\OpenBanking\Config\ClientConfig;
use Shellrent\OpenBanking\Exception\ApiException;
use Shellrent\OpenBanking\Exception\AuthenticationException;
use Shellrent\OpenBanking\Exception\UnexpectedResponseException;
use Shellrent\OpenBanking\Http\Sender;
use Shellrent\OpenBanking\Tests\Support\FrozenClock;
use Shellrent\OpenBanking\Tests\Support\MockHttp;
use Shellrent\OpenBanking\Tests\Unit\TestCase;

#[CoversClass(TokenProvider::class)]
#[CoversClass(InMemoryTokenStore::class)]
final class TokenProviderTest extends TestCase
{
    private FrozenClock $clock;

    #[\Override]
    protected function setUp(): void
    {
        $this->clock = new FrozenClock;
    }

    /**
     * Q-02: client credentials flow with Basic authentication, sent with the mTLS certificate.
     */
    #[Test]
    public function it_requests_a_token_with_the_client_credentials_flow(): void
    {
        $http = new MockHttp(MockHttp::token('token-123', 3600));
        $config = $this->config();

        $token = $this->provider($http, $config)->token();

        self::assertSame('token-123', $token->value);
        self::assertEquals($this->clock->now()->modify('+3600 seconds'), $token->expiresAt);

        $request = $http->request(0);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://external-api.intesasanpaolo.com/auth/oauth/v2/token', (string) $request->getUri());
        self::assertSame('Basic '.base64_encode('client-id:client-secret'), $request->getHeaderLine('Authorization'));
        self::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        self::assertSame('grant_type=client_credentials&scope=oob', (string) $request->getBody());
        self::assertSame($config->certificate->toGuzzleOptions()['cert'], $http->options(0)['cert'] ?? null);
    }

    #[Test]
    public function it_reuses_the_token_until_shortly_before_the_expiry(): void
    {
        $http = new MockHttp(MockHttp::token('first', 3600), MockHttp::token('second', 3600));
        $provider = $this->provider($http);

        self::assertSame('first', $provider->token()->value);

        $this->clock->advance(3600 - TokenProvider::EXPIRY_MARGIN_SECONDS - 1);
        self::assertSame('first', $provider->token()->value);
        self::assertSame(1, $http->count());

        $this->clock->advance(1);
        self::assertSame('second', $provider->token()->value);
        self::assertSame(2, $http->count());
    }

    #[Test]
    public function it_shares_the_token_between_providers_through_the_store(): void
    {
        $http = new MockHttp(MockHttp::token('shared'));
        $store = new InMemoryTokenStore;
        $config = $this->config();

        self::assertSame('shared', $this->provider($http, $config, $store)->token()->value);
        self::assertSame('shared', $this->provider($http, $config, $store)->token()->value);
        self::assertSame(1, $http->count());
    }

    #[Test]
    public function it_requests_a_new_token_after_invalidation(): void
    {
        $http = new MockHttp(MockHttp::token('first'), MockHttp::token('second'));
        $provider = $this->provider($http);

        $provider->token();
        $provider->invalidate();

        self::assertSame('second', $provider->token()->value);
    }

    #[Test]
    public function it_uses_a_token_without_expiry_for_one_request_only(): void
    {
        $http = new MockHttp(
            MockHttp::json(['access_token' => 'first', 'token_type' => 'bearer']),
            MockHttp::json(['access_token' => 'second', 'token_type' => 'Bearer', 'expires_in' => 'not-a-number']),
        );
        $provider = $this->provider($http);

        self::assertSame('first', $provider->token()->value);
        self::assertSame('second', $provider->token()->value);
    }

    #[Test]
    public function it_reports_rejected_credentials_in_the_oauth2_format(): void
    {
        $http = new MockHttp(MockHttp::json(['error' => 'invalid_client', 'error_description' => 'Bad credentials'], 401));

        try {
            $this->provider($http)->token();
            self::fail('AuthenticationException was not thrown');
        } catch (AuthenticationException $exception) {
            self::assertSame(401, $exception->statusCode);
            self::assertSame('invalid_client', $exception->errors[0]->type ?? null);
            self::assertSame('Bad credentials', $exception->errors[0]->description ?? null);
            self::assertStringContainsString('invalid_client: Bad credentials', $exception->getMessage());
        }
    }

    #[Test]
    public function it_reports_server_errors_as_api_errors(): void
    {
        $http = new MockHttp(MockHttp::json(['errors' => [['type' => 'APPLICATION_EXCEPTION', 'code' => 'E500', 'description' => 'Down']]], 503));

        try {
            $this->provider($http)->token();
            self::fail('ApiException was not thrown');
        } catch (AuthenticationException) {
            self::fail('A server error is not an authentication failure');
        } catch (ApiException $exception) {
            self::assertSame(503, $exception->statusCode);
            self::assertSame('APPLICATION_EXCEPTION', $exception->errors[0]->type ?? null);
        }
    }

    #[Test]
    public function it_rejects_a_response_without_a_bearer_token(): void
    {
        $http = new MockHttp(MockHttp::json(['access_token' => 'value', 'token_type' => 'MAC', 'expires_in' => 3600]));

        $this->expectException(UnexpectedResponseException::class);

        $this->provider($http)->token();
    }

    private function provider(MockHttp $http, ?ClientConfig $config = null, TokenStore $store = new InMemoryTokenStore): TokenProvider
    {
        $config ??= $this->config();

        return new TokenProvider($config, new Sender($config, $http->client), $store, $this->clock);
    }
}
