<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Support;

/**
 * Date-only formats used by the APIs (docs/api-quirks.md, Q-10).
 *
 * Date-times are always ISO 8601 and are handled by DateParser::dateTime().
 */
enum DateFormat: string
{
    /**
     * Compact date, e.g. "20180131": account APIs and list filters.
     */
    case Compact = 'Ymd';

    /**
     * Italian date, e.g. "31/01/2018": SCT payment dates and payment list value dates.
     */
    case Italian = 'd/m/Y';
}
