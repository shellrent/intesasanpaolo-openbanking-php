<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Unit\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Shellrent\OpenBanking\Enum\InstantStatus;
use Shellrent\OpenBanking\Enum\PaymentStatus;
use Shellrent\OpenBanking\Enum\ValidationStatus;
use Shellrent\OpenBanking\Tests\Unit\TestCase;

#[CoversClass(ValidationStatus::class)]
#[CoversClass(PaymentStatus::class)]
#[CoversClass(InstantStatus::class)]
final class PaymentStatusesTest extends TestCase
{
    /**
     * Q-25, Q-26: every variant of "accepted" seen in the APIs.
     */
    #[Test]
    #[DataProvider('validationStatuses')]
    public function it_reads_validation_statuses(?string $value, ?ValidationStatus $expected): void
    {
        self::assertSame($expected, ValidationStatus::fromApi($value));
    }

    /**
     * @return iterable<string, array{string|null, ValidationStatus|null}>
     */
    public static function validationStatuses(): iterable
    {
        yield 'SCT and sandbox' => ['ACCP', ValidationStatus::Accepted];
        yield 'SCT Instant in Live' => ['ACSC', ValidationStatus::Accepted];
        yield '2021 spec' => ['ACCP accepted', ValidationStatus::Accepted];
        yield 'seen by the consumer' => ['ACSC acquired', ValidationStatus::Accepted];
        yield 'rejected' => ['RJCT', ValidationStatus::Rejected];
        yield 'rejected with description' => ['RJCT rejected', ValidationStatus::Rejected];
        yield 'unknown' => ['XXXX', null];
        yield 'missing' => [null, null];
    }

    /**
     * Q-27 to Q-29, Q-32.
     */
    #[Test]
    public function it_reads_payment_statuses_with_their_aliases(): void
    {
        self::assertSame(PaymentStatus::Pending, PaymentStatus::fromApi('PNDG'));
        self::assertSame(PaymentStatus::Pending, PaymentStatus::fromApi('PDNG'), 'Spelling used by the FAQ');
        self::assertSame(PaymentStatus::ToVerify, PaymentStatus::fromApi('VRFY'));
        self::assertSame(PaymentStatus::Accepted, PaymentStatus::fromApi('actc'));
        self::assertNull(PaymentStatus::fromApi('ACCP'), 'ACCP is a validation status');
    }

    #[Test]
    public function it_classifies_payment_statuses(): void
    {
        $accepted = array_values(array_filter(PaymentStatus::cases(), static fn (PaymentStatus $status): bool => $status->isAccepted()));
        $final = array_values(array_filter(PaymentStatus::cases(), static fn (PaymentStatus $status): bool => $status->isFinal()));

        self::assertSame([PaymentStatus::Executed, PaymentStatus::InProgress, PaymentStatus::Accepted], $accepted);
        self::assertSame([PaymentStatus::Executed, PaymentStatus::Rejected, PaymentStatus::Canceled, PaymentStatus::Returned], $final);
    }

    /**
     * Q-30: "Cancelled" in the Live status API, "Canceled" elsewhere.
     */
    #[Test]
    public function it_reads_instant_statuses(): void
    {
        self::assertSame(InstantStatus::Canceled, InstantStatus::fromApi('Cancelled'));
        self::assertSame(InstantStatus::Canceled, InstantStatus::fromApi('Canceled'));
        self::assertSame(InstantStatus::Completed, InstantStatus::fromApi('completed'));
        self::assertNull(InstantStatus::fromApi('Unknown'));
    }
}
