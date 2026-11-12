<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Legacy;

use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Base class for the tests of the 2.x code, which is being replaced by the 3.0 rewrite.
 *
 * These tests pin the current behaviour, including the documented quirks
 * (docs/api-quirks.md), so that the rewrite can be checked against them.
 * Delete each test together with the legacy class it covers.
 */
abstract class LegacyTestCase extends TestCase
{
    /**
     * Builds the object the legacy code receives from json_decode().
     *
     * @param  array<string, mixed>  $data
     */
    protected static function decoded(array $data): stdClass
    {
        $decoded = json_decode(json_encode($data, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);

        self::assertInstanceOf(stdClass::class, $decoded);

        return $decoded;
    }
}
