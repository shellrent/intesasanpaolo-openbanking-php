<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Integration;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Shellrent\OpenBanking\Auth\InMemoryTokenStore;
use Shellrent\OpenBanking\Config\ClientConfig;
use Shellrent\OpenBanking\Config\Environment;
use Shellrent\OpenBanking\Config\MutualTlsCertificate;
use Shellrent\OpenBanking\IntesaSanPaoloClient;

/**
 * Base of the integration tests, which call the real APIs (docs/testing.md).
 *
 * They run only when explicitly configured, through environment variables or the file
 * env/integration.env (ignored by git, see env/integration.env.example):
 * - ISP_ENVIRONMENT selects the suite: "sandbox" or "live";
 * - Live also needs ISP_LIVE_READ_ONLY=yes, and every request other than GET (except the token
 *   request) is blocked before it leaves: the Live tests never move money.
 *
 * Credentials and certificates are never committed.
 */
abstract class IntegrationTestCase extends TestCase
{
    private const string ENV_FILE = __DIR__.'/../../env/integration.env';

    private const string TOKEN_PATH = '/auth/oauth/v2/token';

    /** @var array<string, string>|null */
    private static ?array $fileSettings = null;

    private static ?InMemoryTokenStore $tokens = null;

    /**
     * Environment the test class runs against.
     */
    abstract protected static function environment(): Environment;

    /**
     * Settings required by the test class, besides credentials and certificate.
     *
     * @return list<string>
     */
    protected static function requiredSettings(): array
    {
        return [];
    }

    protected function setUp(): void
    {
        $environment = static::environment();

        if (self::setting('ISP_ENVIRONMENT') !== $environment->value) {
            self::markTestSkipped(sprintf('Integration tests against %1$s: set ISP_ENVIRONMENT=%1$s to run them.', $environment->value));
        }

        if ($environment === Environment::Live && self::setting('ISP_LIVE_READ_ONLY') !== 'yes') {
            self::markTestSkipped('Live integration tests: set ISP_LIVE_READ_ONLY=yes to confirm the read-only run.');
        }

        $required = ['ISP_CLIENT_ID', 'ISP_CLIENT_SECRET', 'ISP_CERTIFICATE', ...static::requiredSettings()];
        $missing = array_values(array_filter($required, static fn (string $name): bool => self::setting($name) === null));

        if ($missing !== []) {
            self::markTestSkipped('Missing integration settings: '.implode(', ', $missing).'.');
        }
    }

    /**
     * Client for an account; the token is shared by all the tests of the run.
     */
    protected function client(string $iban): IntesaSanPaoloClient
    {
        $config = new ClientConfig(
            (string) self::setting('ISP_CLIENT_ID'),
            (string) self::setting('ISP_CLIENT_SECRET'),
            $iban,
            self::certificate(),
            static::environment(),
        );

        return new IntesaSanPaoloClient($config, self::http(static::environment()), self::$tokens ??= new InMemoryTokenStore);
    }

    /**
     * A setting from the environment, or else from env/integration.env; empty values count as missing.
     */
    protected static function setting(string $name): ?string
    {
        $value = getenv($name);

        if (! is_string($value) || $value === '') {
            $value = self::fileSettings()[$name] ?? '';
        }

        return $value === '' ? null : $value;
    }

    protected static function requireSetting(string $name): string
    {
        return self::setting($name) ?? throw new LogicException(sprintf('The setting %s is missing.', $name));
    }

    private static function certificate(): MutualTlsCertificate
    {
        $path = self::requireSetting('ISP_CERTIFICATE');
        $passphrase = self::setting('ISP_CERTIFICATE_PASSPHRASE');

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'p12') {
            return MutualTlsCertificate::pkcs12($path, $passphrase);
        }

        return MutualTlsCertificate::pem($path, self::setting('ISP_PRIVATE_KEY'), $passphrase);
    }

    /**
     * In Live, a handler that blocks any request but GET and the token request.
     */
    private static function http(Environment $environment): Client
    {
        $stack = HandlerStack::create();

        if ($environment === Environment::Live) {
            $stack->push(static fn (callable $handler): callable => static function (RequestInterface $request, array $options) use ($handler): mixed {
                $isTokenRequest = $request->getMethod() === 'POST' && $request->getUri()->getPath() === self::TOKEN_PATH;

                if ($request->getMethod() !== 'GET' && ! $isTokenRequest) {
                    throw new LogicException(sprintf('Blocked %s %s: the Live integration tests are read-only.', $request->getMethod(), $request->getUri()->getPath()));
                }

                return $handler($request, $options);
            }, 'read_only_guard');
        }

        return new Client(['handler' => $stack]);
    }

    /**
     * @return array<string, string>
     */
    private static function fileSettings(): array
    {
        if (self::$fileSettings !== null) {
            return self::$fileSettings;
        }

        self::$fileSettings = [];
        $lines = is_readable(self::ENV_FILE) ? file(self::ENV_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;

        foreach ($lines === false ? [] : $lines as $line) {
            if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*?)\s*$/', $line, $matches) === 1) {
                self::$fileSettings[$matches[1]] = trim($matches[2], '"\'');
            }
        }

        return self::$fileSettings;
    }
}
