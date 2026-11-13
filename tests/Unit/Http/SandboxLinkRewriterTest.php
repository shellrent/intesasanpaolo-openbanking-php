<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Config\Environment;
use Shellrent\OpenBanking\Http\SandboxLinkRewriter;
use Shellrent\OpenBanking\Tests\Unit\TestCase;

/**
 * Q-44: the sandbox pagination links point to unreachable hosts.
 */
#[CoversClass(SandboxLinkRewriter::class)]
final class SandboxLinkRewriterTest extends TestCase
{
    #[Test]
    public function it_rewrites_the_transactions_host(): void
    {
        $rewriter = new SandboxLinkRewriter($this->config(Environment::Sandbox));

        self::assertSame(
            'https://external-api.intesasanpaolo.com/twa/sandbox/v1/accounts/X/transactions/today?offsetid=abc',
            $rewriter->rewrite('https://external-api.syssede.systest.sanpaoloimi.com/twa/sandbox/v1/accounts/X/transactions/today?offsetid=abc'),
        );
    }

    #[Test]
    public function it_rewrites_the_payment_lists_host_and_placeholder_iban(): void
    {
        $rewriter = new SandboxLinkRewriter($this->config(Environment::Sandbox));

        self::assertSame(
            'https://external-api.intesasanpaolo.com/twa/sandbox/v1/payments/sct/instant/'.self::IBAN.'/history?offset=100&limit=100',
            $rewriter->rewrite('http://localhost:8081/v1/payments/sct/instant/IT59R0306901001100000002110/history?offset=100&limit=100'),
        );
    }

    #[Test]
    public function it_leaves_live_links_untouched(): void
    {
        $rewriter = new SandboxLinkRewriter($this->config(Environment::Live));
        $link = 'http://localhost:8081/v1/payments/sct/instant/IT59R0306901001100000002110/history?offset=100';

        self::assertSame($link, $rewriter->rewrite($link));
    }
}
