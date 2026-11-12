<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Auth;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Auth\AccessToken;
use Shellrent\OpenBanking\Tests\Unit\TestCase;

#[CoversClass(AccessToken::class)]
final class AccessTokenTest extends TestCase
{
    #[Test]
    public function it_is_valid_until_the_expiry_minus_the_margin(): void
    {
        $token = new AccessToken('value', new DateTimeImmutable('2026-01-15 10:10:00'));

        self::assertTrue($token->isValidAt(new DateTimeImmutable('2026-01-15 10:08:59'), 60));
        self::assertFalse($token->isValidAt(new DateTimeImmutable('2026-01-15 10:09:00'), 60));
        self::assertTrue($token->isValidAt(new DateTimeImmutable('2026-01-15 10:09:59')));
        self::assertFalse($token->isValidAt(new DateTimeImmutable('2026-01-15 10:10:00')));
    }

    #[Test]
    public function it_round_trips_through_an_array(): void
    {
        $token = new AccessToken('value', new DateTimeImmutable('@1768471800'));

        $copy = AccessToken::fromArray($token->toArray());

        self::assertSame('value', $copy->value);
        self::assertSame(1768471800, $copy->expiresAt->getTimestamp());
    }

    #[Test]
    public function it_rejects_invalid_serialized_data(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AccessToken::fromArray(['value' => '', 'expiresAt' => 'tomorrow']);
    }

    #[Test]
    public function it_hides_the_value_from_debug_output(): void
    {
        $dump = print_r(new AccessToken('secret-token', new DateTimeImmutable), true);

        self::assertStringNotContainsString('secret-token', $dump);
    }
}
