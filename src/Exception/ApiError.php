<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Exception;

use stdClass;
use Stringable;

/**
 * A single error reported by the API (docs/api-quirks.md, Q-05).
 */
final readonly class ApiError implements Stringable
{
    public function __construct(
        public ?string $type,
        public ?string $code,
        public ?string $description,
    ) {}

    /**
     * Reads the "errors" value of a response, accepting all the formats seen so far:
     * a list of {type, code, description} objects, objects with {title, detail: {reason}},
     * a single object, or a plain string.
     *
     * @return list<self>
     */
    public static function listFrom(mixed $errors): array
    {
        if ($errors instanceof stdClass) {
            $errors = [$errors];
        }

        if (! is_array($errors)) {
            $description = self::text($errors);

            return $description === null ? [] : [new self(null, null, $description)];
        }

        $list = [];

        foreach ($errors as $error) {
            if (! $error instanceof stdClass) {
                $description = self::text($error);

                if ($description !== null) {
                    $list[] = new self(null, null, $description);
                }

                continue;
            }

            $detail = $error->detail ?? null;

            $list[] = new self(
                self::text($error->type ?? null) ?? self::text($error->title ?? null),
                self::text($error->code ?? null),
                self::text($error->description ?? null)
                    ?? ($detail instanceof stdClass ? self::text($detail->reason ?? null) : self::text($detail)),
            );
        }

        return $list;
    }

    /**
     * Formats the error as "TYPE CODE: description", skipping the missing parts.
     */
    public function __toString(): string
    {
        $label = trim(($this->type ?? '').' '.($this->code ?? ''));

        return match (true) {
            $label === '' => $this->description ?? 'unknown error',
            $this->description === null => $label,
            default => $label.': '.$this->description,
        };
    }

    private static function text(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
