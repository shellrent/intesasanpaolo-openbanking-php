<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Api;

use Shellrent\OpenBanking\Exception\InvalidRequestException;
use Shellrent\OpenBanking\Exception\VopConfirmationRequiredException;
use Shellrent\OpenBanking\Response\Sct\SctSimulation;
use Shellrent\OpenBanking\Response\SctInstant\InstantSimulation;

/**
 * Checks shared by every payment execution (docs/decisions.md, D-36 and D-40).
 *
 * @internal
 */
final class PaymentGuard
{
    /**
     * Tolerance when comparing the simulated amount with the executed one.
     */
    private const float AMOUNT_TOLERANCE = 0.001;

    /**
     * Returns the simulation ID to execute, after checking that:
     * - the simulation was accepted;
     * - it belongs to the payment (same creditor IBAN and amount, when the response reports them);
     * - its Verification of Payee is a full match, or the caller accepted the outcome.
     *
     * @throws InvalidRequestException
     * @throws VopConfirmationRequiredException
     */
    public static function simulationId(
        InstantSimulation|SctSimulation $simulation,
        string $creditorIban,
        float $amount,
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

        if ($simulation->amount !== null && abs($simulation->amount - $amount) > self::AMOUNT_TOLERANCE) {
            throw new InvalidRequestException('The simulation belongs to a payment with a different amount.');
        }

        if ($simulation->vop->requiresConfirmation() && ! $acceptVopResult) {
            throw new VopConfirmationRequiredException($simulation->vop);
        }

        return $simulation->simulationId;
    }
}
