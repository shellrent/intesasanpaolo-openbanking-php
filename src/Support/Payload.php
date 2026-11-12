<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Support;

use DateTimeImmutable;
use InvalidArgumentException;
use Shellrent\OpenBanking\Exception\UnexpectedResponseException;
use stdClass;

/**
 * Typed and tolerant reader of a decoded JSON object.
 *
 * - Every getter accepts several field names and reads the first one that has a value,
 *   to cover the naming variants of the APIs (docs/api-quirks.md, Q-12 and following).
 * - A field that is missing, null or an empty string counts as "no value".
 * - A value of the wrong type is an error: it raises an UnexpectedResponseException that names the field.
 *
 * @internal
 */
final readonly class Payload
{
    public function __construct(
        private stdClass $data,
        private string $path = 'payload',
        private ?string $transactionId = null,
    ) {}

    /**
     * Whether at least one of the fields has a value.
     */
    public function has(string ...$keys): bool
    {
        return $this->first($keys) !== null;
    }

    public function string(string ...$keys): ?string
    {
        $found = $this->first($keys);

        if ($found === null) {
            return null;
        }

        [$key, $value] = $found;

        return match (true) {
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            default => throw $this->unexpected($key, 'a string', $value),
        };
    }

    public function requireString(string ...$keys): string
    {
        return $this->string(...$keys) ?? throw $this->missing($keys);
    }

    public function float(string ...$keys): ?float
    {
        $found = $this->first($keys);

        if ($found === null) {
            return null;
        }

        [$key, $value] = $found;

        return match (true) {
            is_int($value), is_float($value) => (float) $value,
            is_string($value) && is_numeric($value) => (float) $value,
            default => throw $this->unexpected($key, 'a number', $value),
        };
    }

    public function requireFloat(string ...$keys): float
    {
        return $this->float(...$keys) ?? throw $this->missing($keys);
    }

    public function int(string ...$keys): ?int
    {
        $found = $this->first($keys);

        if ($found === null) {
            return null;
        }

        [$key, $value] = $found;

        if (is_int($value)) {
            return $value;
        }

        if ((is_float($value) || is_string($value)) && is_numeric($value) && (float) $value === floor((float) $value)) {
            return (int) $value;
        }

        throw $this->unexpected($key, 'an integer', $value);
    }

    public function requireInt(string ...$keys): int
    {
        return $this->int(...$keys) ?? throw $this->missing($keys);
    }

    /**
     * Reads a boolean, also accepting "true"/"false" strings and 1/0 (docs/api-quirks.md, Q-20).
     */
    public function bool(string ...$keys): ?bool
    {
        $found = $this->first($keys);

        if ($found === null) {
            return null;
        }

        [$key, $value] = $found;

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value) && in_array(strtolower($value), ['true', 'false'], true)) {
            return strtolower($value) === 'true';
        }

        if ($value === 1 || $value === 0) {
            return $value === 1;
        }

        throw $this->unexpected($key, 'a boolean', $value);
    }

    /**
     * Reads a date without time; "0" counts as no value (docs/api-quirks.md, Q-23).
     *
     * @param  DateFormat|non-empty-list<DateFormat>  $formats  Expected format, or alternative formats (see DateParser::date()).
     */
    public function date(DateFormat|array $formats, string ...$keys): ?DateTimeImmutable
    {
        $formats = $formats instanceof DateFormat ? [$formats] : $formats;

        $found = $this->first($keys);

        if ($found === null) {
            return null;
        }

        [$key, $value] = $found;

        if (is_int($value)) {
            $value = (string) $value;
        }

        if (! is_string($value)) {
            throw $this->unexpected($key, 'a date', $value);
        }

        if ($value === '0') {
            return null;
        }

        try {
            return DateParser::date($value, ...$formats);
        } catch (InvalidArgumentException $exception) {
            throw new UnexpectedResponseException(
                sprintf('Unexpected value for %s: %s', $this->pathOf($key), $exception->getMessage()),
                $this->transactionId,
                $exception,
            );
        }
    }

    /**
     * Reads an ISO 8601 date-time.
     */
    public function dateTime(string ...$keys): ?DateTimeImmutable
    {
        $found = $this->first($keys);

        if ($found === null) {
            return null;
        }

        [$key, $value] = $found;

        if (! is_string($value)) {
            throw $this->unexpected($key, 'a date-time', $value);
        }

        try {
            return DateParser::dateTime($value);
        } catch (InvalidArgumentException $exception) {
            throw new UnexpectedResponseException(
                sprintf('Unexpected value for %s: %s', $this->pathOf($key), $exception->getMessage()),
                $this->transactionId,
                $exception,
            );
        }
    }

    public function object(string ...$keys): ?self
    {
        $found = $this->first($keys);

        if ($found === null) {
            return null;
        }

        [$key, $value] = $found;

        if (! $value instanceof stdClass) {
            throw $this->unexpected($key, 'an object', $value);
        }

        return new self($value, $this->pathOf($key), $this->transactionId);
    }

    public function requireObject(string ...$keys): self
    {
        return $this->object(...$keys) ?? throw $this->missing($keys);
    }

    /**
     * Reads a list of objects; a missing list is an empty list.
     *
     * @return list<self>
     */
    public function list(string ...$keys): array
    {
        $found = $this->first($keys);

        if ($found === null) {
            return [];
        }

        [$key, $value] = $found;

        if (! is_array($value) || ! array_is_list($value)) {
            throw $this->unexpected($key, 'a list', $value);
        }

        $items = [];

        foreach ($value as $index => $item) {
            $itemPath = sprintf('%s[%d]', $this->pathOf($key), $index);

            if (! $item instanceof stdClass) {
                throw new UnexpectedResponseException(
                    sprintf('Unexpected value for %s: expected an object, got %s.', $itemPath, get_debug_type($item)),
                    $this->transactionId,
                );
            }

            $items[] = new self($item, $itemPath, $this->transactionId);
        }

        return $items;
    }

    /**
     * The underlying decoded object.
     */
    public function raw(): stdClass
    {
        return $this->data;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function transactionId(): ?string
    {
        return $this->transactionId;
    }

    /**
     * @param  array<string>  $keys
     * @return array{string, mixed}|null
     */
    private function first(array $keys): ?array
    {
        foreach ($keys as $key) {
            if (! property_exists($this->data, $key)) {
                continue;
            }

            $value = $this->data->{$key};

            if ($value !== null && $value !== '') {
                return [$key, $value];
            }
        }

        return null;
    }

    private function pathOf(string $key): string
    {
        return $this->path.'.'.$key;
    }

    private function unexpected(string $key, string $expected, mixed $value): UnexpectedResponseException
    {
        return new UnexpectedResponseException(
            sprintf('Unexpected value for %s: expected %s, got %s.', $this->pathOf($key), $expected, get_debug_type($value)),
            $this->transactionId,
        );
    }

    /**
     * @param  array<string>  $keys
     */
    private function missing(array $keys): UnexpectedResponseException
    {
        return new UnexpectedResponseException(
            sprintf('Missing required field %s.', implode(' or ', array_map($this->pathOf(...), $keys))),
            $this->transactionId,
        );
    }
}
