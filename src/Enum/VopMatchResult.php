<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Enum;

/**
 * Outcome of the Verification of Payee: whether the creditor name matches the IBAN (docs/api-quirks.md, Q-37).
 *
 * The API may send either ISO-like codes (MTCH, CMTC, NMTC, NOAP) or the labels of the SmartHub FAQ
 * (MATCH, CLOSE MATCH, NO MATCH, NOT PARTIC, OUT OF SCOPE, NO APP, CHECK NOT POSSIBLE): both are recognized.
 */
enum VopMatchResult: string
{
    /**
     * Name and IBAN match.
     */
    case Match = 'MTCH';

    /**
     * Partial match: the bank suggests the registered name (see VopResult::$matchedName).
     */
    case CloseMatch = 'CMTC';

    /**
     * Name and IBAN do not match.
     */
    case NoMatch = 'NMTC';

    /**
     * The creditor bank could not verify for reasons tied to the account, e.g. closed or nonexistent ("NO APP").
     */
    case NotApplicable = 'NOAP';

    /**
     * The creditor bank does not take part in the service although it is required to ("NOT PARTIC").
     */
    case NotParticipating = 'NOTPARTIC';

    /**
     * The creditor bank does not take part in the service and is not required to ("OUT OF SCOPE").
     */
    case OutOfScope = 'OUTOFSCOPE';

    /**
     * The verification could not be carried out ("CHECK NOT POSSIBLE").
     */
    case NotPossible = 'CHECKNOTPOSSIBLE';

    private const array ALIASES = [
        'MATCH' => self::Match,
        'CLOSEMATCH' => self::CloseMatch,
        'NOMATCH' => self::NoMatch,
        'NOAPP' => self::NotApplicable,
        'NOTAPPLICABLE' => self::NotApplicable,
        'NOTPARTICIPATING' => self::NotParticipating,
        'CHECKNOTPOSSIBILE' => self::NotPossible,
        'NOTPOSSIBLE' => self::NotPossible,
    ];

    /**
     * Recognizes codes and labels ignoring case, spaces and punctuation; null for unknown values.
     */
    public static function fromApi(?string $value): ?self
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Za-z]/', '', (string) $value));

        if ($normalized === '') {
            return null;
        }

        return self::tryFrom($normalized) ?? self::ALIASES[$normalized] ?? null;
    }
}
