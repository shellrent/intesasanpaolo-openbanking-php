<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Enum;

/**
 * Status of an account transaction (docs/api-quirks.md, Q-23).
 */
enum TransactionStatus: string
{
    use FromApiValue;

    case Accounted = 'accounted';
    case Reversed = 'reversed';
    case Canceled = 'canceled';

    /**
     * Intraday transactions not yet accounted.
     */
    case Unaccounted = 'unaccounted';

    /**
     * @return array<string, self>
     */
    private static function aliases(): array
    {
        return ['cancelled' => self::Canceled];
    }
}
