<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Request;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Exception\InvalidRequestException;
use Shellrent\OpenBanking\Request\InstantPayment;
use Shellrent\OpenBanking\Request\Validator;
use Shellrent\OpenBanking\Tests\Unit\TestCase;

#[CoversClass(InstantPayment::class)]
#[CoversClass(Validator::class)]
final class InstantPaymentTest extends TestCase
{
    #[Test]
    public function it_normalizes_the_values(): void
    {
        $payment = new InstantPayment(
            creditorName: '  Mario Rossi ',
            creditorIban: 'it00 x000 0000 0000 0000 0000 002',
            amount: '0.3',
            paymentInformation: ' Fattura 1/2026 ',
            debtorName: 'Example Srl',
            debtorIban: 'IT00X0000000000000000000001',
            endToEndId: ' NOT PROVIDED ',
            siaCode: '',
        );

        self::assertSame('Mario Rossi', $payment->creditorName);
        self::assertSame('IT00X0000000000000000000002', $payment->creditorIban);
        self::assertSame('0.30', $payment->amount, 'D-65: normalized to two decimals');
        self::assertSame('Fattura 1/2026', $payment->paymentInformation);
        self::assertSame('IT00X0000000000000000000001', $payment->debtorIban);
        self::assertNull($payment->endToEndId, '"NOTPROVIDED" means no end-to-end ID (Q-35)');
        self::assertNull($payment->siaCode);
    }

    #[Test]
    public function it_keeps_the_texts_as_they_are(): void
    {
        $payment = new InstantPayment('Società Rossi & Figli', self::IBAN, '10', 'Caffè n° 1', 'Example Srl');

        self::assertSame('Società Rossi & Figli', $payment->creditorName);
        self::assertSame('Caffè n° 1', $payment->paymentInformation);
        self::assertNull($payment->debtorIban);
    }

    #[Test]
    public function it_counts_characters_not_bytes(): void
    {
        $payment = new InstantPayment(str_repeat('à', 70), self::IBAN, '1', str_repeat('è', 140), 'Example Srl');

        self::assertSame(70, (int) preg_match_all('/./su', $payment->creditorName));
    }

    /**
     * D-65: amounts are decimal strings, validated strictly and normalized to two decimals.
     */
    #[Test]
    #[DataProvider('validAmounts')]
    public function it_accepts_decimal_amounts(string $amount, string $expected): void
    {
        self::assertSame($expected, (new InstantPayment('Mario Rossi', self::IBAN, $amount, 'Fattura', 'Example Srl'))->amount);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validAmounts(): iterable
    {
        yield 'one cent' => ['0.01', '0.01'];
        yield 'one decimal' => ['0.3', '0.30'];
        yield 'two decimals' => ['0.30', '0.30'];
        yield 'common amount' => ['1250.10', '1250.10'];
        yield 'one decimal, thousands' => ['1250.1', '1250.10'];
        yield 'integer' => ['1250', '1250.00'];
        yield 'largest' => ['999999999999.99', '999999999999.99'];
    }

    /**
     * @param  array{creditorName?: string, creditorIban?: string, amount?: string, paymentInformation?: string, debtorName?: string, debtorIban?: string, endToEndId?: string}  $overrides
     */
    #[Test]
    #[DataProvider('invalidValues')]
    public function it_rejects_invalid_values(array $overrides, string $message): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage($message);

        new InstantPayment(
            creditorName: $overrides['creditorName'] ?? 'Mario Rossi',
            creditorIban: $overrides['creditorIban'] ?? self::IBAN,
            amount: $overrides['amount'] ?? '10.00',
            paymentInformation: $overrides['paymentInformation'] ?? 'Fattura',
            debtorName: $overrides['debtorName'] ?? 'Example Srl',
            debtorIban: $overrides['debtorIban'] ?? null,
            endToEndId: $overrides['endToEndId'] ?? null,
        );
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'empty creditor name' => [['creditorName' => '  '], 'creditor name must not be empty'];
        yield 'creditor name too long' => [['creditorName' => str_repeat('a', 71)], 'creditor name must be at most 70'];
        yield 'empty debtor name' => [['debtorName' => ''], 'debtor name must not be empty'];
        yield 'payment information too long' => [['paymentInformation' => str_repeat('a', 141)], 'payment information must be at most 140'];
        yield 'invalid creditor IBAN' => [['creditorIban' => 'IT00'], 'creditor IBAN is not a valid IBAN'];
        yield 'invalid debtor IBAN' => [['debtorIban' => 'not an iban'], 'debtor IBAN is not a valid IBAN'];
        yield 'zero amount' => [['amount' => '0'], 'greater than zero'];
        yield 'zero amount with decimals' => [['amount' => '0.00'], 'greater than zero'];
        yield 'negative amount' => [['amount' => '-1'], 'is not valid'];
        yield 'three decimals' => [['amount' => '1.005'], 'is not valid'];
        yield 'exponent' => [['amount' => '1e3'], 'is not valid'];
        yield 'decimal comma' => [['amount' => '1,50'], 'is not valid'];
        yield 'thousands separator' => [['amount' => '1.250,00'], 'is not valid'];
        yield 'space' => [['amount' => '1 250.00'], 'is not valid'];
        yield 'surrounding spaces' => [['amount' => ' 10.00'], 'is not valid'];
        yield 'leading zero' => [['amount' => '01.50'], 'is not valid'];
        yield 'missing integer part' => [['amount' => '.50'], 'is not valid'];
        yield 'missing decimals' => [['amount' => '10.'], 'is not valid'];
        yield 'plus sign' => [['amount' => '+10'], 'is not valid'];
        yield 'too many integer digits' => [['amount' => '1000000000000.00'], 'is not valid'];
        yield 'empty amount' => [['amount' => ''], 'is not valid'];
        yield 'end-to-end ID too long' => [['endToEndId' => str_repeat('E', 37)], 'end-to-end ID must be at most 36'];
        yield 'creditor name not in UTF-8' => [['creditorName' => "Societ\xE0 Rossi"], 'creditor name is not valid UTF-8'];
        yield 'payment information not in UTF-8' => [['paymentInformation' => "Caff\xE8"], 'payment information is not valid UTF-8'];
    }
}
