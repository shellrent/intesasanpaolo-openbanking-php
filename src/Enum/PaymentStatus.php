<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Enum;

/**
 * Status of an executed payment, as ISO 20022 codes (docs/api-quirks.md, Q-27 to Q-29 and Q-32).
 *
 * Used by the SCT Instant execution and by all the SCT APIs.
 */
enum PaymentStatus: string
{
    use FromApiValue;

    /**
     * Payment executed (ACSC).
     */
    case Executed = 'ACSC';

    /**
     * SCT: funds blocked, execution in progress (ACSP).
     */
    case InProgress = 'ACSP';

    /**
     * SCT: order accepted, funds not yet blocked, e.g. future date, after the cut-off, non-working day (ACTC).
     */
    case Accepted = 'ACTC';

    /**
     * SCT Instant: execution on hold for checks; can be resubmitted with the resubmit ID (PNDG, also written "PDNG").
     */
    case Pending = 'PNDG';

    /**
     * Order rejected by the bank (RJCT).
     */
    case Rejected = 'RJCT';

    /**
     * Status not available yet: check again later. For SCT Instant, look for the payment in the
     * payment list after 10 minutes: if it is there it was executed, otherwise it failed (VRFY).
     */
    case ToVerify = 'VRFY';

    /**
     * SCT: order canceled by the customer (CANC).
     */
    case Canceled = 'CANC';

    /**
     * SCT: payment recalled (RETU).
     */
    case Returned = 'RETU';

    /**
     * The bank took the order: it is executed or will be, so it must not be paid again.
     */
    public function isAccepted(): bool
    {
        return match ($this) {
            self::Executed, self::InProgress, self::Accepted => true,
            default => false,
        };
    }

    /**
     * The status will not change any more.
     */
    public function isFinal(): bool
    {
        return match ($this) {
            self::Executed, self::Rejected, self::Canceled, self::Returned => true,
            default => false,
        };
    }

    /**
     * @return array<string, self>
     */
    private static function aliases(): array
    {
        return ['pdng' => self::Pending];
    }
}
