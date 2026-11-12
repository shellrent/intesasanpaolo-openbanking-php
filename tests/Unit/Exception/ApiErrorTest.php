<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Exception\ApiError;
use Shellrent\OpenBanking\Exception\ApiException;
use Shellrent\OpenBanking\Exception\AuthenticationException;
use Shellrent\OpenBanking\Tests\Unit\TestCase;

/**
 * Q-05: all the error formats seen so far.
 */
#[CoversClass(ApiError::class)]
#[CoversClass(ApiException::class)]
final class ApiErrorTest extends TestCase
{
    #[Test]
    public function it_reads_the_standard_error_list(): void
    {
        $errors = ApiError::listFrom(self::decode('[{"type":"INPUT_EXCEPTION","code":"E001","description":"Invalid IBAN"}]'));

        self::assertEquals([new ApiError('INPUT_EXCEPTION', 'E001', 'Invalid IBAN')], $errors);
        self::assertSame('INPUT_EXCEPTION E001: Invalid IBAN', (string) $errors[0]);
    }

    #[Test]
    public function it_reads_the_title_and_detail_format(): void
    {
        $errors = ApiError::listFrom(self::decode('[{"status":"400","title":"Bad Request","detail":{"reason":"Missing date"}}]'));

        self::assertEquals([new ApiError('Bad Request', null, 'Missing date')], $errors);
        self::assertSame('Bad Request: Missing date', (string) $errors[0]);
    }

    #[Test]
    public function it_reads_single_objects_strings_and_numeric_codes(): void
    {
        self::assertEquals([new ApiError(null, '42', null)], ApiError::listFrom(self::decode('{"code":42}')));
        self::assertEquals([new ApiError(null, null, 'Service unavailable')], ApiError::listFrom('Service unavailable'));
        self::assertEquals([new ApiError(null, null, 'First')], ApiError::listFrom(['First', '', false]));
        self::assertSame([], ApiError::listFrom(null));
        self::assertSame([], ApiError::listFrom(''));
    }

    #[Test]
    public function it_formats_errors_with_missing_parts(): void
    {
        self::assertSame('E001', (string) new ApiError(null, 'E001', null));
        self::assertSame('Only a description', (string) new ApiError(null, null, 'Only a description'));
        self::assertSame('unknown error', (string) new ApiError(null, null, null));
    }

    #[Test]
    public function it_builds_exceptions_of_the_calling_class(): void
    {
        $exception = AuthenticationException::fromResponse(401, null, 'TX-1', '');

        self::assertInstanceOf(AuthenticationException::class, $exception);
        self::assertSame('Intesa Sanpaolo API error (HTTP 401): no error details in the response', $exception->getMessage());
        self::assertNull($exception->responseBody);
    }

    #[Test]
    public function it_truncates_long_response_bodies(): void
    {
        $exception = ApiException::fromResponse(500, null, null, str_repeat('x', ApiException::MAX_BODY_LENGTH + 100));

        self::assertSame(ApiException::MAX_BODY_LENGTH, strlen((string) $exception->responseBody));
    }

    private static function decode(string $json): mixed
    {
        return json_decode($json, false, 512, JSON_THROW_ON_ERROR);
    }
}
