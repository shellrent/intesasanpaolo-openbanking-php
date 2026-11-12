<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Exception\UnexpectedResponseException;
use Shellrent\OpenBanking\Support\DateFormat;
use Shellrent\OpenBanking\Support\Payload;
use Shellrent\OpenBanking\Tests\Unit\TestCase;
use stdClass;

#[CoversClass(Payload::class)]
final class PayloadTest extends TestCase
{
    /**
     * Q-12, Q-13: the first field name with a value wins; empty strings fall through to the next name.
     */
    #[Test]
    public function it_reads_the_first_field_name_with_a_value(): void
    {
        $payload = self::payload(['debtorIBAN' => '', 'debtorIban' => 'IT00X1', 'order-id' => 'ORD-1']);

        self::assertSame('IT00X1', $payload->string('debtorIBAN', 'debtorIban'));
        self::assertSame('ORD-1', $payload->string('orderId', 'order-id'));
        self::assertNull($payload->string('missing'));
        self::assertTrue($payload->has('missing', 'debtorIban'));
        self::assertFalse($payload->has('debtorIBAN'));
    }

    #[Test]
    public function it_converts_numbers_to_strings_and_numeric_strings_to_numbers(): void
    {
        $payload = self::payload(['paymentId' => 12345, 'amount' => '1250.50', 'fees' => 0, 'count' => '3', 'total' => 4.0]);

        self::assertSame('12345', $payload->string('paymentId'));
        self::assertSame(1250.5, $payload->float('amount'));
        self::assertSame(0.0, $payload->float('fees'));
        self::assertSame(3, $payload->int('count'));
        self::assertSame(4, $payload->int('total'));
    }

    /**
     * Q-20: booleans may arrive as strings.
     */
    #[Test]
    public function it_reads_booleans_in_several_forms(): void
    {
        $payload = self::payload(['a' => true, 'b' => 'true', 'c' => 'FALSE', 'd' => 0]);

        self::assertTrue($payload->bool('a'));
        self::assertTrue($payload->bool('b'));
        self::assertFalse($payload->bool('c'));
        self::assertFalse($payload->bool('d'));
    }

    /**
     * Q-10, Q-23: dates use explicit formats and "0" means no date.
     */
    #[Test]
    public function it_reads_dates_with_explicit_formats(): void
    {
        $payload = self::payload([
            'valueDate' => '02/01/2022',
            'inquiryDate' => 20180131,
            'emptyDate' => '0',
            'executionDate' => '2018-05-04T09:52:10.289+0200',
        ]);

        self::assertSame('2022-01-02', $payload->date(DateFormat::Italian, 'valueDate')?->format('Y-m-d'));
        self::assertSame('2018-01-31', $payload->date(DateFormat::Compact, 'inquiryDate')?->format('Y-m-d'));
        self::assertNull($payload->date(DateFormat::Compact, 'emptyDate'));
        self::assertSame('2018-05-04T09:52:10+02:00', $payload->dateTime('executionDate')?->format(DATE_ATOM));
    }

    #[Test]
    public function it_reads_nested_objects_and_lists(): void
    {
        $payload = self::payload([
            'links' => ['next' => 'https://example.test/next'],
            'items' => [['id' => 'A'], ['id' => 'B']],
        ]);

        self::assertSame('https://example.test/next', $payload->requireObject('links')->string('next'));
        self::assertSame(['A', 'B'], array_map(static fn (Payload $item): ?string => $item->string('id'), $payload->list('items')));
        self::assertSame([], $payload->list('missing'));
        self::assertSame('payload.items[1]', $payload->list('items')[1]->path());
    }

    #[Test]
    public function it_names_the_field_and_the_transaction_in_type_errors(): void
    {
        $payload = self::payload(['items' => [['amount' => 'ten']]], 'TX-9');

        try {
            $payload->list('items')[0]->float('amount');
            self::fail('UnexpectedResponseException was not thrown');
        } catch (UnexpectedResponseException $exception) {
            self::assertSame('Unexpected value for payload.items[0].amount: expected a number, got string.', $exception->getMessage());
            self::assertSame('TX-9', $exception->transactionId);
        }
    }

    #[Test]
    public function it_names_all_the_alternatives_of_a_missing_required_field(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Missing required field payload.orderId or payload.order-id.');

        self::payload([])->requireString('orderId', 'order-id');
    }

    #[Test]
    public function it_reports_invalid_dates_with_the_field_name(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('payload.valueDate');

        self::payload(['valueDate' => '31/02/2022'])->date(DateFormat::Italian, 'valueDate');
    }

    #[Test]
    public function it_rejects_values_of_the_wrong_type(): void
    {
        $payload = self::payload(['flag' => 'maybe', 'list' => ['a' => 1], 'object' => 'text', 'date' => true]);

        foreach ([
            static fn () => $payload->bool('flag'),
            static fn () => $payload->list('list'),
            static fn () => $payload->object('object'),
            static fn () => $payload->date(DateFormat::Compact, 'date'),
            static fn () => $payload->int('flag'),
        ] as $read) {
            try {
                $read();
                self::fail('UnexpectedResponseException was not thrown');
            } catch (UnexpectedResponseException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function payload(array $data, ?string $transactionId = null): Payload
    {
        $decoded = json_decode(json_encode((object) $data, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        self::assertInstanceOf(stdClass::class, $decoded);

        return new Payload($decoded, 'payload', $transactionId);
    }
}
