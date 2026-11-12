<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Support;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Support\DateFormat;
use Shellrent\OpenBanking\Support\DateParser;
use Shellrent\OpenBanking\Tests\Unit\TestCase;

#[CoversClass(DateParser::class)]
final class DateParserTest extends TestCase
{
    /**
     * Q-09: "02/01/2022" is January 2nd, not February 1st as `new DateTime()` would read it.
     */
    #[Test]
    public function it_reads_italian_dates_as_day_month_year(): void
    {
        $date = DateParser::date('02/01/2022', DateFormat::Italian);

        self::assertSame('2022-01-02 00:00:00 Europe/Rome', $date->format('Y-m-d H:i:s e'));
    }

    #[Test]
    public function it_reads_compact_dates_at_midnight_in_rome(): void
    {
        self::assertSame('2018-01-31 00:00:00 Europe/Rome', DateParser::date('20180131', DateFormat::Compact)->format('Y-m-d H:i:s e'));
    }

    #[Test]
    #[DataProvider('invalidDates')]
    public function it_rejects_invalid_dates(string $value, DateFormat $format): void
    {
        $this->expectException(InvalidArgumentException::class);

        DateParser::date($value, $format);
    }

    /**
     * @return iterable<string, array{string, DateFormat}>
     */
    public static function invalidDates(): iterable
    {
        yield 'day out of range' => ['32/01/2022', DateFormat::Italian];
        yield 'nonexistent day' => ['31/02/2022', DateFormat::Italian];
        yield 'trailing data' => ['02/01/2022 10:00', DateFormat::Italian];
        yield 'wrong format' => ['2022-01-02', DateFormat::Italian];
        yield 'italian date as compact' => ['02/01/2022', DateFormat::Compact];
        yield 'compact month out of range' => ['20221301', DateFormat::Compact];
    }

    /**
     * @param  non-empty-string  $expected
     */
    #[Test]
    #[DataProvider('isoDateTimes')]
    public function it_reads_iso_8601_date_times(string $value, string $expected): void
    {
        self::assertSame($expected, DateParser::dateTime($value)->format('Y-m-d\TH:i:s.vP'));
    }

    /**
     * @return iterable<string, array{string, non-empty-string}>
     */
    public static function isoDateTimes(): iterable
    {
        yield 'UTC' => ['2021-03-23T10:15:30Z', '2021-03-23T10:15:30.000+00:00'];
        yield 'offset without colon and milliseconds' => ['2018-05-04T09:52:10.289+0200', '2018-05-04T09:52:10.289+02:00'];
        yield 'offset with colon' => ['2018-05-04T09:52:10+02:00', '2018-05-04T09:52:10.000+02:00'];
        yield 'leap second of the spec examples' => ['2017-12-31T23:59:60Z', '2018-01-01T00:00:00.000+00:00'];
        yield 'no offset is read in Rome' => ['2026-07-01T12:00:00', '2026-07-01T12:00:00.000+02:00'];
    }

    #[Test]
    public function it_rejects_values_that_are_not_iso_8601(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DateParser::dateTime('02/01/2022');
    }

    /**
     * Request dates use the calendar date of the given object, without time zone conversion.
     */
    #[Test]
    public function it_formats_the_calendar_date_of_the_given_object(): void
    {
        $lateEveningUtc = new DateTimeImmutable('2026-01-31 23:30:00', new DateTimeZone('UTC'));

        self::assertSame('20260131', DateParser::format($lateEveningUtc, DateFormat::Compact));
        self::assertSame('31/01/2026', DateParser::format($lateEveningUtc, DateFormat::Italian));
    }
}
