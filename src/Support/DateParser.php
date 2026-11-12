<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

/**
 * Parses and formats API dates with explicit formats (docs/dates-and-formats.md).
 *
 * Never use `new DateTime($value)` on API values: PHP reads "02/01/2022" as February 1st
 * (docs/api-quirks.md, Q-09).
 *
 * @internal
 */
final class DateParser
{
    /**
     * Time zone of the dates without a time: they are Italian business dates.
     */
    public const string TIMEZONE = 'Europe/Rome';

    private const string ISO_DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}$/';

    private const string ISO_DATE_FORMAT = 'Y-m-d';

    private const string ISO_8601_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,9})?(?:Z|[+-]\d{2}:?\d{2})?$/';

    /**
     * Parses a date without time, at midnight in Europe/Rome.
     *
     * Besides the expected format, an ISO date ("2022-01-02") or ISO date-time is always accepted:
     * it cannot be misread, and the 2.x SDK accepted it through `new DateTime()`. A date-time keeps its time.
     *
     * Alternative formats can be given for fields whose format varies; they must be structurally
     * different from each other (e.g. "Ymd" and "d/m/Y"), so that no value can match two of them.
     *
     * @throws InvalidArgumentException When the value does not match the formats or is not a real date (e.g. 31/02/2022).
     */
    public static function date(string $value, DateFormat $format, DateFormat ...$alternatives): DateTimeImmutable
    {
        // A full ISO 8601 date-time is unambiguous as well: the 2.x SDK accepted it through `new DateTime()`.
        if (preg_match(self::ISO_8601_PATTERN, $value) === 1) {
            return self::dateTime($value);
        }

        $patterns = preg_match(self::ISO_DATE_PATTERN, $value) === 1
            ? [self::ISO_DATE_FORMAT]
            : array_map(static fn (DateFormat $candidate): string => $candidate->value, [$format, ...$alternatives]);

        foreach ($patterns as $pattern) {
            $date = DateTimeImmutable::createFromFormat('!'.$pattern, $value, self::timezone());
            $errors = DateTimeImmutable::getLastErrors();

            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date;
            }
        }

        throw new InvalidArgumentException(sprintf('"%s" is not a valid date in the "%s" format.', $value, implode('" or "', $patterns)));
    }

    /**
     * Parses an ISO 8601 date-time, e.g. "2017-12-31T23:59:60Z" or "2018-05-04T09:52:10.289+0200".
     *
     * The offset is kept; a value without offset is read in Europe/Rome.
     *
     * @throws InvalidArgumentException When the value is not an ISO 8601 date-time.
     */
    public static function dateTime(string $value): DateTimeImmutable
    {
        if (preg_match(self::ISO_8601_PATTERN, $value) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not an ISO 8601 date-time.', $value));
        }

        try {
            return new DateTimeImmutable($value, self::timezone());
        } catch (Exception $exception) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid ISO 8601 date-time.', $value), 0, $exception);
        }
    }

    /**
     * Formats a date for a request parameter, using the calendar date of the given object as it is
     * (no time zone conversion).
     */
    public static function format(DateTimeInterface $date, DateFormat $format): string
    {
        return $date->format($format->value);
    }

    public static function timezone(): DateTimeZone
    {
        return new DateTimeZone(self::TIMEZONE);
    }
}
