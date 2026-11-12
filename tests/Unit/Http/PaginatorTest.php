<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Http;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Auth\TokenProvider;
use Shellrent\OpenBanking\Config\Environment;
use Shellrent\OpenBanking\Exception\UnexpectedResponseException;
use Shellrent\OpenBanking\Http\ApiResponse;
use Shellrent\OpenBanking\Http\Paginator;
use Shellrent\OpenBanking\Http\SandboxLinkRewriter;
use Shellrent\OpenBanking\Http\Sender;
use Shellrent\OpenBanking\Http\Transport;
use Shellrent\OpenBanking\Tests\Support\MockHttp;
use Shellrent\OpenBanking\Tests\Unit\TestCase;

#[CoversClass(Paginator::class)]
final class PaginatorTest extends TestCase
{
    private const string LIVE_BASE = 'https://external-api.intesasanpaolo.com/twa/live/v1';

    #[Test]
    public function it_follows_the_next_links_until_the_last_page(): void
    {
        $http = new MockHttp(
            MockHttp::token(),
            MockHttp::envelope(['page' => 1, 'nextPage' => self::LIVE_BASE.'/items?offsetid=p2']),
            MockHttp::envelope(['page' => 2, 'nextPage' => self::LIVE_BASE.'/items?offsetid=p3']),
            MockHttp::envelope(['page' => 3, 'nextPage' => '']),
        );

        $pages = $this->collect($this->paginator($http)->pages('items', ['date' => '20260115'], self::nextPage(), ['Content-Type' => 'application/json']));

        self::assertSame([1, 2, 3], array_map(static fn (ApiResponse $page): ?int => $page->requirePayload()->int('page'), $pages));
        self::assertSame(self::LIVE_BASE.'/items?date=20260115', (string) $http->request(1)->getUri());
        self::assertSame(self::LIVE_BASE.'/items?offsetid=p2', (string) $http->request(2)->getUri());
        self::assertSame(self::LIVE_BASE.'/items?offsetid=p3', (string) $http->request(3)->getUri());
        self::assertSame('application/json', $http->request(3)->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function it_rewrites_the_sandbox_links(): void
    {
        $http = new MockHttp(
            MockHttp::token(),
            MockHttp::envelope(['nextPage' => 'https://external-api.syssede.systest.sanpaoloimi.com/twa/sandbox/v1/items?offsetid=p2']),
            MockHttp::envelope([]),
        );

        $this->collect($this->paginator($http, Environment::Sandbox)->pages('items', [], self::nextPage()));

        self::assertSame('https://external-api.intesasanpaolo.com/twa/sandbox/v1/items?offsetid=p2', (string) $http->request(2)->getUri());
    }

    #[Test]
    public function it_stops_on_a_pagination_loop(): void
    {
        $http = new MockHttp(
            MockHttp::token(),
            MockHttp::envelope(['nextPage' => self::LIVE_BASE.'/items?offsetid=same']),
            MockHttp::envelope(['nextPage' => self::LIVE_BASE.'/items?offsetid=same']),
        );

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Pagination loop');

        $this->collect($this->paginator($http)->pages('items', [], self::nextPage()));
    }

    #[Test]
    public function it_stops_after_the_maximum_number_of_pages(): void
    {
        $http = new MockHttp(
            MockHttp::token(),
            MockHttp::envelope(['nextPage' => self::LIVE_BASE.'/items?offsetid=p2']),
            MockHttp::envelope(['nextPage' => self::LIVE_BASE.'/items?offsetid=p3']),
        );

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('more than 2 pages');

        $this->collect($this->paginator($http, maxPages: 2)->pages('items', [], self::nextPage()));
    }

    /**
     * @return Closure(ApiResponse): ?string
     */
    private static function nextPage(): Closure
    {
        return static fn (ApiResponse $response): ?string => $response->requirePayload()->string('nextPage');
    }

    /**
     * @param  iterable<ApiResponse>  $pages
     * @return list<ApiResponse>
     *
     * @phpstan-impure
     */
    private function collect(iterable $pages): array
    {
        $collected = [];

        foreach ($pages as $page) {
            $collected[] = $page;
        }

        return $collected;
    }

    private function paginator(MockHttp $http, Environment $environment = Environment::Live, int $maxPages = Paginator::DEFAULT_MAX_PAGES): Paginator
    {
        $config = $this->config($environment);
        $sender = new Sender($config, $http->client);
        $transport = new Transport($config, $sender, new TokenProvider($config, $sender));

        return new Paginator($transport, new SandboxLinkRewriter($config), $maxPages);
    }
}
