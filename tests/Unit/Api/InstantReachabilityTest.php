<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Api\SctInstantApi;
use Shellrent\OpenBanking\Config\Environment;
use Shellrent\OpenBanking\Exception\InvalidRequestException;
use Shellrent\OpenBanking\IntesaSanPaoloClient;
use Shellrent\OpenBanking\Response\SctInstant\InstantReachability;
use Shellrent\OpenBanking\Tests\Support\MockHttp;
use Shellrent\OpenBanking\Tests\Unit\TestCase;

/**
 * Q-18: SCT Instant reachability of an IBAN.
 */
#[CoversClass(SctInstantApi::class)]
#[CoversClass(InstantReachability::class)]
final class InstantReachabilityTest extends TestCase
{
    #[Test]
    public function it_checks_whether_an_iban_can_receive_instant_payments(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::fixture('SctInstant/reachability', ['X-ISP-Transaction-ID' => 'TX-R']));

        $reachability = $this->api($http)->reachability('it00 x000 0000 0000 0000 0000 002');

        self::assertSame(
            'https://external-api.intesasanpaolo.com/twa/live/v1/payments/sct/instant/reachability?iban=IT00X0000000000000000000002',
            (string) $http->request(1)->getUri(),
        );
        self::assertTrue($reachability->isReachable());
        self::assertSame('IBAN reachable', $reachability->status);
        self::assertSame('BCITITMM', $reachability->bic);
        self::assertSame('TX-R', $reachability->transactionId);
    }

    #[Test]
    public function it_reports_an_unreachable_iban(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::envelope(['isReachable' => false, 'IBAN_Status' => 'IBAN not reachable']));

        $reachability = $this->api($http)->reachability('IT00X0000000000000000000002');

        self::assertFalse($reachability->isReachable());
        self::assertFalse($reachability->reachable);
        self::assertNull($reachability->bic);
    }

    #[Test]
    public function it_rejects_a_malformed_iban(): void
    {
        $this->expectException(InvalidRequestException::class);

        $this->api(new MockHttp)->reachability('not an iban');
    }

    private function api(MockHttp $http): SctInstantApi
    {
        return (new IntesaSanPaoloClient($this->config(Environment::Live), $http->client))->sctInstant();
    }
}
