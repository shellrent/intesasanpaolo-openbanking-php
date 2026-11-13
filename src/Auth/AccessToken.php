<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Auth;

use DateTimeImmutable;
use InvalidArgumentException;
use SensitiveParameter;

/**
 * OAuth2 bearer token with its expiry.
 *
 * It is a credential: store it only in private storage (see TokenStore).
 */
final readonly class AccessToken
{
    public function __construct(
        #[SensitiveParameter] public string $value,
        public DateTimeImmutable $expiresAt,
    ) {}

    /**
     * Whether the token can still be used at the given time, keeping a safety margin before the expiry.
     */
    public function isValidAt(DateTimeImmutable $now, int $marginSeconds = 0): bool
    {
        return $now->modify(sprintf('+%d seconds', $marginSeconds)) < $this->expiresAt;
    }

    /**
     * Serializable form, for TokenStore implementations backed by external caches.
     *
     * @return array{value: string, expiresAt: int}
     */
    public function toArray(): array
    {
        return ['value' => $this->value, 'expiresAt' => $this->expiresAt->getTimestamp()];
    }

    /**
     * Rebuilds a token from toArray().
     *
     * @param  array<mixed>  $data
     *
     * @throws InvalidArgumentException When the data is not a serialized token.
     */
    public static function fromArray(array $data): self
    {
        $value = $data['value'] ?? null;
        $expiresAt = $data['expiresAt'] ?? null;

        if (! is_string($value) || $value === '' || ! is_int($expiresAt)) {
            throw new InvalidArgumentException('The data is not a serialized access token.');
        }

        return new self($value, (new DateTimeImmutable)->setTimestamp($expiresAt));
    }

    /**
     * Keeps the token value out of var_dump() and debug output.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['value' => '***', 'expiresAt' => $this->expiresAt];
    }
}
