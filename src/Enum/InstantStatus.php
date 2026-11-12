<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Enum;

/**
 * Status of an SCT Instant payment in the status and list APIs (docs/api-quirks.md, Q-30).
 */
enum InstantStatus: string
{
    use FromApiValue;

    case Completed = 'Completed';

    /**
     * Written "Canceled" in the list and in the sandbox, "Cancelled" in the Live status API.
     */
    case Canceled = 'Canceled';

    case Rejected = 'Rejected';

    case Returned = 'Returned';

    /**
     * @return array<string, self>
     */
    private static function aliases(): array
    {
        return ['cancelled' => self::Canceled];
    }
}
