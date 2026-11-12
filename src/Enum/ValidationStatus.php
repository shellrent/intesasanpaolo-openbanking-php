<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Enum;

/**
 * Outcome of a payment simulation (docs/api-quirks.md, Q-25 and Q-26).
 */
enum ValidationStatus: string
{
    use FromApiValue;

    /**
     * The payment can be executed. SCT and the sandbox answer "ACCP"; SCT Instant in Live answers
     * "ACSC"; older versions appended a description ("ACCP accepted", "ACSC acquired").
     */
    case Accepted = 'ACCP';

    case Rejected = 'RJCT';

    /**
     * @return array<string, self>
     */
    private static function aliases(): array
    {
        return ['acsc' => self::Accepted];
    }
}
