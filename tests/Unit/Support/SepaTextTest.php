<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Support\SepaText;
use Shellrent\OpenBanking\Tests\Unit\TestCase;

/**
 * Q-39: characters accepted by the bank in payment texts.
 */
#[CoversClass(SepaText::class)]
final class SepaTextTest extends TestCase
{
    #[Test]
    public function it_tells_whether_a_text_uses_only_accepted_characters(): void
    {
        self::assertTrue(SepaText::isValid("Fattura 1/2026 - Rossi (acconto) d'Amico, ok? +10:00."));
        self::assertTrue(SepaText::isValid(''));
        self::assertFalse(SepaText::isValid('Società'));
        self::assertFalse(SepaText::isValid('Rossi & Figli'));
        self::assertFalse(SepaText::isValid('Fattura n° 1'));
        self::assertFalse(SepaText::isValid('mail@example.test'));
    }

    #[Test]
    public function it_transliterates_and_removes_the_other_characters(): void
    {
        $sanitized = SepaText::sanitize('  Società Rossi & Figli – Fattura n° 1 “saldo” l’ultimo  ');

        self::assertSame("Societa Rossi + Figli - Fattura n 1 saldo l'ultimo", $sanitized);
        self::assertTrue(SepaText::isValid($sanitized));
    }

    #[Test]
    public function it_truncates_to_the_maximum_length(): void
    {
        self::assertSame('Fattura', SepaText::sanitize('Fattura numero 1', 8), 'Trailing spaces are trimmed after truncation');
        self::assertSame('Perche', SepaText::sanitize('Perché', 10));
    }
}
