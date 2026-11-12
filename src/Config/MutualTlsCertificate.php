<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Config;

use SensitiveParameter;
use Shellrent\OpenBanking\Exception\ConfigurationException;

/**
 * Client certificate for the mutual TLS authentication required by the APIs (docs/api-quirks.md, Q-01 and Q-08).
 *
 * The certificate is downloaded from the SmartHub reserved area ("Certificates Management")
 * and is the same for the Live and Sandbox environments.
 */
final readonly class MutualTlsCertificate
{
    private function __construct(
        public string $certificatePath,
        #[SensitiveParameter] private ?string $certificatePassphrase,
        public ?string $privateKeyPath,
        #[SensitiveParameter] private ?string $privateKeyPassphrase,
    ) {
        self::assertReadable($certificatePath, 'certificate');

        if ($privateKeyPath !== null) {
            self::assertReadable($privateKeyPath, 'private key');
        }
    }

    /**
     * PEM certificate, with the private key either in the same file or in a separate one.
     */
    public static function pem(
        string $certificatePath,
        ?string $privateKeyPath = null,
        #[SensitiveParameter] ?string $privateKeyPassphrase = null,
        #[SensitiveParameter] ?string $certificatePassphrase = null,
    ): self {
        return new self(
            $certificatePath,
            self::nullIfEmpty($certificatePassphrase),
            self::nullIfEmpty($privateKeyPath),
            self::nullIfEmpty($privateKeyPassphrase),
        );
    }

    /**
     * PKCS#12 bundle containing both the certificate and the private key.
     *
     * The file must have the ".p12" extension: Guzzle relies on it to tell cURL the certificate type.
     */
    public static function pkcs12(string $path, #[SensitiveParameter] ?string $passphrase = null): self
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'p12') {
            throw new ConfigurationException(sprintf(
                'The PKCS#12 certificate "%s" must have the ".p12" extension, which Guzzle uses to detect the certificate type.',
                $path,
            ));
        }

        return new self($path, self::nullIfEmpty($passphrase), null, null);
    }

    /**
     * Guzzle request options that attach the certificate to a request.
     *
     * @internal
     *
     * @return array{cert: string|array{string, string}, ssl_key?: string|array{string, string}}
     */
    public function toGuzzleOptions(): array
    {
        $options = [
            'cert' => $this->certificatePassphrase === null
                ? $this->certificatePath
                : [$this->certificatePath, $this->certificatePassphrase],
        ];

        if ($this->privateKeyPath !== null) {
            $options['ssl_key'] = $this->privateKeyPassphrase === null
                ? $this->privateKeyPath
                : [$this->privateKeyPath, $this->privateKeyPassphrase];
        }

        return $options;
    }

    /**
     * Keeps the passphrases out of var_dump() and debug output.
     *
     * @return array<string, string|null>
     */
    public function __debugInfo(): array
    {
        return [
            'certificatePath' => $this->certificatePath,
            'certificatePassphrase' => $this->certificatePassphrase === null ? null : '***',
            'privateKeyPath' => $this->privateKeyPath,
            'privateKeyPassphrase' => $this->privateKeyPassphrase === null ? null : '***',
        ];
    }

    private static function assertReadable(string $path, string $description): void
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new ConfigurationException(sprintf('The mutual TLS %s file "%s" does not exist or is not readable.', $description, $path));
        }
    }

    private static function nullIfEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
