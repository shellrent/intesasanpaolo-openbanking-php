<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Exception;

use RuntimeException;
use Shellrent\OpenBanking\Response\VopResult;

/**
 * The payment was not executed because the Verification of Payee of its simulation is not a full match.
 *
 * Executing a payment counts as accepting the VoP outcome (docs/api-quirks.md, Q-37): review the
 * result ($vop) and, if the payment is still wanted, execute it again with `acceptVopResult: true`.
 */
final class VopConfirmationRequiredException extends RuntimeException implements OpenBankingException
{
    public function __construct(public readonly VopResult $vop)
    {
        parent::__construct(sprintf(
            'The Verification of Payee is not a full match (%s): confirm the payment explicitly with acceptVopResult: true.',
            $vop->summary(),
        ));
    }
}
