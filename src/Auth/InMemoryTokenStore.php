<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Auth;

use Override;

/**
 * Keeps tokens in memory for the life of the object.
 *
 * Share one instance between clients to reuse the token within the same process.
 */
final class InMemoryTokenStore implements TokenStore
{
    /**
     * @var array<string, AccessToken>
     */
    private array $tokens = [];

    #[Override]
    public function get(string $key): ?AccessToken
    {
        return $this->tokens[$key] ?? null;
    }

    #[Override]
    public function put(string $key, AccessToken $token): void
    {
        $this->tokens[$key] = $token;
    }

    #[Override]
    public function forget(string $key): void
    {
        unset($this->tokens[$key]);
    }
}
