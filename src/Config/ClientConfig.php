<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Config;

use SensitiveParameter;
use Shellrent\OpenBanking\Exception\ConfigurationException;

/**
 * Configuration of the SDK: credentials, account, environment, certificate and timeouts.
 */
final readonly class ClientConfig
{
    /**
     * Host of the Intesa Sanpaolo API gateway, shared by all environments.
     */
    public const string HOST = 'https://external-api.intesasanpaolo.com';

    public const float DEFAULT_CONNECT_TIMEOUT = 10.0;

    public const float DEFAULT_TIMEOUT = 60.0;

    /**
     * IBAN of the account used by the API calls, normalized (uppercase, without spaces).
     */
    public string $iban;

    /**
     * @param  string  $clientId  Client ID of the SmartHub application.
     * @param  string  $clientSecret  Client secret of the SmartHub application.
     * @param  string  $iban  IBAN of the account; spaces are removed and letters uppercased.
     * @param  MutualTlsCertificate  $certificate  Client certificate for mutual TLS, required by all the APIs.
     * @param  Environment  $environment  Target environment; defaults to the sandbox, so Live must be chosen explicitly.
     * @param  float  $connectTimeout  Maximum seconds to establish the connection.
     * @param  float  $timeout  Maximum seconds for a whole request.
     */
    public function __construct(
        public string $clientId,
        #[SensitiveParameter] private string $clientSecret,
        string $iban,
        public MutualTlsCertificate $certificate,
        public Environment $environment = Environment::Sandbox,
        public float $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT,
        public float $timeout = self::DEFAULT_TIMEOUT,
    ) {
        if (trim($clientId) === '') {
            throw new ConfigurationException('The client ID must not be empty.');
        }

        if (trim($clientSecret) === '') {
            throw new ConfigurationException('The client secret must not be empty.');
        }

        $this->iban = self::normalizeIban($iban);

        foreach (['connectTimeout' => $connectTimeout, 'timeout' => $timeout] as $name => $seconds) {
            if (! is_finite($seconds) || $seconds < 0.001) {
                throw new ConfigurationException(sprintf('The %s must be a positive number of seconds.', $name));
            }
        }
    }

    /**
     * Base URI of the APIs for the configured environment, e.g. "https://external-api.intesasanpaolo.com/twa/live/v1".
     */
    public function apiBaseUri(): string
    {
        return sprintf('%s/twa/%s/v1', self::HOST, $this->environment->value);
    }

    /**
     * URI of the OAuth2 token endpoint, which is the same for every environment.
     */
    public function tokenUri(): string
    {
        return self::HOST.'/auth/oauth/v2/token';
    }

    /**
     * Value of the "Authorization" header for the OAuth2 token request.
     *
     * @internal
     */
    public function basicAuthorization(): string
    {
        return 'Basic '.base64_encode($this->clientId.':'.$this->clientSecret);
    }

    /**
     * Keeps the client secret out of var_dump() and debug output.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'clientId' => $this->clientId,
            'clientSecret' => '***',
            'iban' => $this->iban,
            'certificate' => $this->certificate,
            'environment' => $this->environment,
            'connectTimeout' => $this->connectTimeout,
            'timeout' => $this->timeout,
        ];
    }

    private static function normalizeIban(string $iban): string
    {
        $normalized = strtoupper((string) preg_replace('/\s+/', '', $iban));

        if (preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $normalized) !== 1) {
            throw new ConfigurationException('The IBAN is not valid.');
        }

        return $normalized;
    }
}
