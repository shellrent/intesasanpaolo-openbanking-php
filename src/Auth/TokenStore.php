<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Auth;

/**
 * Storage of OAuth2 tokens, to share them between client instances and avoid a login per instance.
 *
 * The default implementation keeps tokens in memory. To share them across processes, implement
 * this interface on top of an application cache (Laravel, Phalcon, Redis, files, ...), using
 * AccessToken::toArray()/fromArray() for serialization and the token expiry as TTL.
 *
 * Tokens are credentials: the storage must not be readable by third parties.
 */
interface TokenStore
{
    public function get(string $key): ?AccessToken;

    public function put(string $key, AccessToken $token): void;

    public function forget(string $key): void;
}
