<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Api\IbanToolsApi;
use Shellrent\OpenBanking\Config\Environment;
use Shellrent\OpenBanking\Exception\ApiException;
use Shellrent\OpenBanking\Exception\InvalidRequestException;
use Shellrent\OpenBanking\IntesaSanPaoloClient;
use Shellrent\OpenBanking\Response\Iban\IbanFormalCheck;
use Shellrent\OpenBanking\Response\Iban\IbanVerification;
use Shellrent\OpenBanking\Tests\Support\MockHttp;
use Shellrent\OpenBanking\Tests\Unit\TestCase;

#[CoversClass(IbanToolsApi::class)]
#[CoversClass(IbanFormalCheck::class)]
#[CoversClass(IbanVerification::class)]
final class IbanToolsApiTest extends TestCase
{
    private const string BASE = 'https://external-api.intesasanpaolo.com/twa/live/v1/tools/iban';

    private const string CHECKED_IBAN = 'IT00X0000000000000000000002';

    #[Test]
    public function it_checks_the_format_of_an_iban(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::fixture('Iban/formal-check', ['X-ISP-Transaction-ID' => 'TX-F']));

        $check = $this->api($http)->formalCheck(' it00 x000 0000 0000 0000 0000 002 ');

        self::assertSame(self::BASE.'/formal-check?iban='.self::CHECKED_IBAN, (string) $http->request(1)->getUri());
        self::assertTrue($check->isValid());
        self::assertSame('IT', $check->countryCode);
        self::assertSame('00', $check->checkDigits);
        self::assertSame('00000', $check->bankCode, 'Q-19: leading zeros are kept');
        self::assertSame('00000', $check->bankAgency);
        self::assertSame('000000000001', $check->account);
        self::assertSame('X0000000000000000000001', $check->bban);
        self::assertSame('TX-F', $check->transactionId);
    }

    /**
     * Q-19: the spec declares numbers; they are read as strings (leading zeros are lost only if the API sends numbers).
     */
    #[Test]
    public function it_reads_numeric_parts_as_strings(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::envelope(['isValid' => true, 'checkDigits' => 4, 'bankCode' => 3069]));

        $check = $this->api($http)->formalCheck(self::CHECKED_IBAN);

        self::assertSame('4', $check->checkDigits);
        self::assertSame('3069', $check->bankCode);
    }

    /**
     * An invalid IBAN is an outcome of the API, not an error of the SDK.
     */
    #[Test]
    public function it_sends_invalid_ibans_to_the_formal_check(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::envelope(['isValid' => false]));

        $check = $this->api($http)->formalCheck('IT00 NOT VALID');

        self::assertSame(self::BASE.'/formal-check?iban=IT00NOTVALID', (string) $http->request(1)->getUri());
        self::assertFalse($check->isValid());
        self::assertFalse($check->valid);
    }

    #[Test]
    public function it_rejects_an_empty_iban_for_the_formal_check(): void
    {
        $this->expectException(InvalidRequestException::class);

        $this->api(new MockHttp)->formalCheck('   ');
    }

    /**
     * D-51: with the IBAN alone, chargeabilityMulti is the existence check.
     */
    #[Test]
    public function it_verifies_the_existence_of_an_iban(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::fixture('Iban/verify'));

        $verification = $this->api($http)->verify(self::CHECKED_IBAN);

        self::assertSame(self::BASE.'/chargeabilityMulti?iban='.self::CHECKED_IBAN, (string) $http->request(1)->getUri());
        self::assertTrue($verification->isAllowed());
        self::assertFalse($verification->isTransferred());
        self::assertNull($verification->additionalInfo);
    }

    #[Test]
    public function it_verifies_the_holder_of_an_iban(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::fixture('Iban/verify'), MockHttp::fixture('Iban/verify'));
        $api = $this->api($http);

        $api->verify(self::CHECKED_IBAN, fiscalCode: 'aaaaaa00a00a000a');
        $api->verify(self::CHECKED_IBAN, vatNumber: '012 345 678 90');

        self::assertSame(self::BASE.'/chargeabilityMulti?iban='.self::CHECKED_IBAN.'&fiscalCode=AAAAAA00A00A000A', (string) $http->request(1)->getUri());
        self::assertSame(self::BASE.'/chargeabilityMulti?iban='.self::CHECKED_IBAN.'&vatNumber=01234567890', (string) $http->request(2)->getUri());
    }

    /**
     * Q-20: "isAllowed" as the string "true", and "AC15" for a transferred account.
     */
    #[Test]
    public function it_reads_the_alternative_outcome_format(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::fixture('Iban/verify-transferred'));

        $verification = $this->api($http)->verify(self::CHECKED_IBAN);

        self::assertTrue($verification->isAllowed());
        self::assertTrue($verification->isTransferred());
        self::assertSame('account', $verification->ibanType);
    }

    #[Test]
    public function it_reports_a_negative_outcome(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::envelope(['allowed' => false, 'additionalInfo' => 'AC15']));

        $verification = $this->api($http)->verify(self::CHECKED_IBAN, vatNumber: '01234567890');

        self::assertFalse($verification->isAllowed());
        self::assertTrue($verification->isTransferred(), 'Moved to another bank');
    }

    /**
     * Q-20: some negative outcomes arrive with "success: false" and no errors, and are still an outcome.
     */
    #[Test]
    public function it_reports_a_negative_outcome_sent_as_unsuccessful(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::json([
            'success' => false,
            'payload' => ['isAllowed' => false, 'additionalInfo' => 'vatNumber is mandatory for companies accounts'],
        ]));

        $verification = $this->api($http)->verify(self::CHECKED_IBAN, fiscalCode: 'AAAAAA00A00A000A');

        self::assertFalse($verification->isAllowed());
        self::assertSame('vatNumber is mandatory for companies accounts', $verification->additionalInfo);
    }

    #[Test]
    public function it_never_reports_a_positive_outcome_inside_an_unsuccessful_response(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::json(['success' => false, 'payload' => ['isAllowed' => true]]));

        self::assertFalse($this->api($http)->verify(self::CHECKED_IBAN, vatNumber: '01234567890')->isAllowed());
    }

    #[Test]
    public function it_still_reports_unsuccessful_responses_with_errors(): void
    {
        $http = new MockHttp(MockHttp::token(), MockHttp::json([
            'success' => false,
            'errors' => [['type' => 'WRONG_INPUT_EXCEPTION', 'code' => 'WI05', 'description' => 'At least one field among fiscalCode and vatNumber must be present']],
            'payload' => ['isAllowed' => false],
        ]));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('WI05');

        $this->api($http)->verify(self::CHECKED_IBAN);
    }

    #[Test]
    public function it_rejects_malformed_input_before_calling_the_api(): void
    {
        $api = $this->api(new MockHttp);

        foreach ([
            'IBAN' => static fn () => $api->verify('IT00'),
            'fiscal code' => static fn () => $api->verify(self::CHECKED_IBAN, fiscalCode: 'RSS-MRA'),
            'VAT number' => static fn () => $api->verify(self::CHECKED_IBAN, vatNumber: '123'),
        ] as $field => $call) {
            try {
                $call();
                self::fail("InvalidRequestException was not thrown for the {$field}");
            } catch (InvalidRequestException $exception) {
                self::assertStringContainsString($field, $exception->getMessage());
            }
        }
    }

    private function api(MockHttp $http): IbanToolsApi
    {
        return (new IntesaSanPaoloClient($this->config(Environment::Live), $http->client))->ibanTools();
    }
}
