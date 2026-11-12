<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Auth\InMemoryTokenStore;
use Shellrent\OpenBanking\IntesaSanPaoloClient;
use Shellrent\OpenBanking\Tests\Support\MockHttp;

#[CoversClass(IntesaSanPaoloClient::class)]
final class IntesaSanPaoloClientTest extends TestCase
{
    #[Test]
    public function it_exposes_the_configuration_and_reuses_the_api_objects(): void
    {
        $config = $this->config();
        $client = new IntesaSanPaoloClient($config);

        self::assertSame($config, $client->config());
        self::assertSame($client->accounts(), $client->accounts());
    }

    #[Test]
    public function it_shares_the_token_between_clients_with_the_same_store(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::fixture('Accounts/balance'), MockHttp::fixture('Accounts/balance'));
        $store = new InMemoryTokenStore;
        $config = $this->config();

        (new IntesaSanPaoloClient($config, $http->client, $store))->accounts()->balance();
        (new IntesaSanPaoloClient($config, $http->client, $store))->accounts()->balance();

        self::assertSame(3, $http->count(), 'One token request and two balance requests');
        self::assertSame('POST', $http->request(0)->getMethod());
        self::assertSame('GET', $http->request(2)->getMethod());
    }
}
