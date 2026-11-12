<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Config\MutualTlsCertificate;
use Shellrent\OpenBanking\Exception\ConfigurationException;
use Shellrent\OpenBanking\Tests\Unit\TestCase;

#[CoversClass(MutualTlsCertificate::class)]
final class MutualTlsCertificateTest extends TestCase
{
    #[Test]
    public function it_builds_the_guzzle_options_of_a_pem_certificate(): void
    {
        $certificate = $this->temporaryFile('pem');
        $key = $this->temporaryFile('key');

        self::assertSame(
            ['cert' => $certificate, 'ssl_key' => $key],
            MutualTlsCertificate::pem($certificate, $key)->toGuzzleOptions(),
        );

        self::assertSame(
            ['cert' => [$certificate, 'cert-pass'], 'ssl_key' => [$key, 'key-pass']],
            MutualTlsCertificate::pem($certificate, $key, 'key-pass', 'cert-pass')->toGuzzleOptions(),
        );

        self::assertSame(['cert' => $certificate], MutualTlsCertificate::pem($certificate)->toGuzzleOptions());
    }

    #[Test]
    public function it_builds_the_guzzle_options_of_a_pkcs12_bundle(): void
    {
        $bundle = $this->temporaryFile('p12');

        self::assertSame(['cert' => [$bundle, 'secret']], MutualTlsCertificate::pkcs12($bundle, 'secret')->toGuzzleOptions());
        self::assertSame(['cert' => $bundle], MutualTlsCertificate::pkcs12($bundle)->toGuzzleOptions());
    }

    /**
     * Like 2.x, an empty passphrase means "no passphrase".
     */
    #[Test]
    public function it_treats_empty_passphrases_as_missing(): void
    {
        $bundle = $this->temporaryFile('p12');

        self::assertSame(['cert' => $bundle], MutualTlsCertificate::pkcs12($bundle, '')->toGuzzleOptions());
    }

    #[Test]
    public function it_requires_the_p12_extension_for_pkcs12_bundles(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('.p12');

        MutualTlsCertificate::pkcs12($this->temporaryFile('pfx'));
    }

    #[Test]
    public function it_rejects_a_missing_certificate_file(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('certificate');

        MutualTlsCertificate::pem('/path/that/does/not/exist.pem');
    }

    #[Test]
    public function it_rejects_a_missing_private_key_file(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('private key');

        MutualTlsCertificate::pem($this->temporaryFile('pem'), '/path/that/does/not/exist.key');
    }

    #[Test]
    public function it_hides_the_passphrases_from_debug_output(): void
    {
        $dump = print_r(MutualTlsCertificate::pem($this->temporaryFile('pem'), $this->temporaryFile('key'), 'key-pass', 'cert-pass'), true);

        self::assertStringNotContainsString('key-pass', $dump);
        self::assertStringNotContainsString('cert-pass', $dump);
    }
}
