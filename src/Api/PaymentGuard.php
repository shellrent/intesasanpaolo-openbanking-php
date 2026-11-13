<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Api;

use Shellrent\OpenBanking\Exception\InvalidRequestException;
use Shellrent\OpenBanking\Exception\VopConfirmationRequiredException;
use Shellrent\OpenBanking\Response\Sct\SctSimulation;
use Shellrent\OpenBanking\Response\SctInstant\InstantSimulation;
use Shellrent\OpenBanking\Support\SepaText;

/**
 * Checks shared by every payment execution (docs/decisions.md, D-36 and D-40).
 *
 * @internal
 */
final class PaymentGuard
{
    /**
     * Returns the simulation ID to execute, after checking that:
     * - the simulation was accepted;
     * - it belongs to the payment (same creditor IBAN, name and amount, when the response reports them):
     *   the Verification of Payee outcome applies only to the simulated name (D-62);
     * - its Verification of Payee is a full match, or the caller accepted the outcome.
     *
     * @throws InvalidRequestException
     * @throws VopConfirmationRequiredException
     */
    public static function simulationId(
        InstantSimulation|SctSimulation $simulation,
        string $creditorIban,
        string $creditorName,
        string $amount,
        bool $acceptVopResult,
    ): string {
        if (! $simulation->isAccepted() || $simulation->simulationId === null) {
            throw new InvalidRequestException(sprintf(
                'The simulation was not accepted (validation status: %s): the payment cannot be executed.',
                $simulation->rawValidationStatus ?? 'none',
            ));
        }

        $simulatedIban = $simulation->creditor->iban;

        if ($simulatedIban !== null && strtoupper((string) preg_replace('/\s+/', '', $simulatedIban)) !== $creditorIban) {
            throw new InvalidRequestException('The simulation belongs to a payment with a different creditor IBAN.');
        }

        $simulatedName = $simulation->creditor->name;

        if ($simulatedName !== null && self::comparableName($simulatedName) !== self::comparableName($creditorName)) {
            throw new InvalidRequestException(
                'The simulation belongs to a payment with a different creditor name: simulate the payment again,'
                .' because the Verification of Payee outcome applies to the simulated name.',
            );
        }

        // Both amounts are decimal strings with two decimals: compared exactly, without tolerance (D-65).
        if ($simulation->amount !== null && $simulation->amount !== $amount) {
            throw new InvalidRequestException('The simulation belongs to a payment with a different amount.');
        }

        if ($simulation->vop->requiresConfirmation() && ! $acceptVopResult) {
            throw new VopConfirmationRequiredException($simulation->vop);
        }

        return $simulation->simulationId;
    }

    /**
     * Name reduced to the SEPA characters, in lowercase and with single spaces, so that the bank's own
     * transliteration or spacing of the echoed name does not count as a different name.
     */
    private static function comparableName(string $name): string
    {
        return strtolower(trim((string) preg_replace('/\s+/', ' ', SepaText::sanitize($name))));
    }
}
