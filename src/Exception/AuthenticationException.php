<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Exception;

/**
 * Authentication failed: the OAuth2 token request was rejected, or an API call answered HTTP 401.
 *
 * After an HTTP 401 the cached token is discarded, so the next call requests a new one.
 */
final class AuthenticationException extends ApiException {}
