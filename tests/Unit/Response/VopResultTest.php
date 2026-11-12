<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Enum\VopMatchResult;
use Shellrent\OpenBanking\Http\ApiResponse;
use Shellrent\OpenBanking\Response\VopResult;
use Shellrent\OpenBanking\Tests\Unit\TestCase;

/**
 * Q-37: Verification of Payee.
 */
#[CoversClass(VopResult::class)]
#[CoversClass(VopMatchResult::class)]
final class VopResultTest extends TestCase
{
    #[Test]
    #[DataProvider('matchResults')]
    public function it_recognizes_codes_and_labels(string $value, VopMatchResult $expected): void
    {
        self::assertSame($expected, VopMatchResult::fromApi($value));
    }

    /**
     * @return iterable<string, array{string, VopMatchResult}>
     */
    public static function matchResults(): iterable
    {
        yield 'MTCH' => ['MTCH', VopMatchResult::Match];
        yield 'MATCH' => ['MATCH', VopMatchResult::Match];
        yield 'CMTC' => ['CMTC', VopMatchResult::CloseMatch];
        yield 'CLOSE MATCH' => ['CLOSE MATCH', VopMatchResult::CloseMatch];
        yield 'NMTC' => ['NMTC', VopMatchResult::NoMatch];
        yield 'no match, lowercase' => ['no match', VopMatchResult::NoMatch];
        yield 'NOAP' => ['NOAP', VopMatchResult::NotApplicable];
        yield 'NO APP' => ['NO APP', VopMatchResult::NotApplicable];
        yield 'NOT PARTIC' => ['NOT PARTIC', VopMatchResult::NotParticipating];
        yield 'OUT OF SCOPE' => ['OUT OF SCOPE', VopMatchResult::OutOfScope];
        yield 'CHECK NOT POSSIBLE' => ['CHECK NOT POSSIBLE', VopMatchResult::NotPossible];
        yield 'FAQ spelling' => ['CHECK NOT POSSIBILE', VopMatchResult::NotPossible];
        yield 'underscores' => ['OUT_OF_SCOPE', VopMatchResult::OutOfScope];
    }

    #[Test]
    public function it_reads_the_outcome_from_the_response_headers(): void
    {
        $vop = VopResult::fromResponse(new ApiResponse(200, null, [
            'vopMatchResult' => ['CMTC'],
            'vopMatchedName' => ['Mario Rossi'],
            'vopResultDescription' => [''],
        ], null));

        self::assertSame(VopMatchResult::CloseMatch, $vop->result);
        self::assertSame('CMTC', $vop->rawResult);
        self::assertSame('Mario Rossi', $vop->matchedName);
        self::assertNull($vop->description);
        self::assertTrue($vop->wasPerformed());
        self::assertFalse($vop->isMatch());
        self::assertTrue($vop->requiresConfirmation());
        self::assertSame('CMTC, suggested name: Mario Rossi', $vop->summary());
    }

    #[Test]
    public function it_requires_confirmation_when_the_outcome_is_missing_or_unknown(): void
    {
        $missing = VopResult::fromResponse(new ApiResponse(200, null, [], null));
        $unknown = new VopResult(VopMatchResult::fromApi('SOMETHING NEW'), 'SOMETHING NEW');

        self::assertFalse($missing->wasPerformed());
        self::assertTrue($missing->requiresConfirmation());
        self::assertSame('no verification outcome in the response', $missing->summary());
        self::assertNull($unknown->result);
        self::assertTrue($unknown->requiresConfirmation());
    }

    #[Test]
    public function it_round_trips_through_an_array(): void
    {
        $vop = new VopResult(VopMatchResult::NoMatch, 'NMTC', null, 'Name does not match');

        self::assertEquals($vop, VopResult::fromArray($vop->toArray()));
    }
}
