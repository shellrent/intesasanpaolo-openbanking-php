<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Response\SctInstant;

use DateTimeImmutable;
use InvalidArgumentException;
use Shellrent\OpenBanking\Enum\ValidationStatus;
use Shellrent\OpenBanking\Http\ApiResponse;
use Shellrent\OpenBanking\Response\Party;
use Shellrent\OpenBanking\Response\VopResult;
use Shellrent\OpenBanking\Support\DateParser;
use Shellrent\OpenBanking\Support\Decimal;
use Shellrent\OpenBanking\Support\EndToEndId;

/**
 * Result of an SCT Instant simulation (POST /payments/sct/instant/simulation).
 *
 * The simulation ID is valid for 5 minutes (docs/api-quirks.md, Q-33). To execute the payment in another
 * process, save the simulation with toArray() and restore it with fromArray().
 */
final readonly class InstantSimulation
{
    /**
     * @param  ValidationStatus|null  $validationStatus  Outcome, or null for an unknown value: see $rawValidationStatus.
     * @param  string|null  $endToEndId  End-to-end ID; null when none was sent ("NOTPROVIDED").
     * @param  DateTimeImmutable|null  $date  When the simulation was performed.
     * @param  VopResult  $vop  Verification of Payee: executing the payment counts as accepting it.
     * @param  string|null  $transactionId  "X-ISP-Transaction-ID" of the response, for the ISP support.
     */
    public function __construct(
        public ?ValidationStatus $validationStatus,
        public ?string $rawValidationStatus,
        public ?string $simulationId,
        public ?string $transactionStatusDescription,
        public ?string $endToEndId,
        public ?string $amount,
        public ?string $currency,
        public ?DateTimeImmutable $date,
        public ?string $categoryPurpose,
        public ?string $paymentInformation,
        public Party $debtor,
        public Party $creditor,
        public VopResult $vop,
        public ?string $transactionId = null,
    ) {}

    /**
     * Whether the bank accepted the simulation, so the payment can be executed.
     */
    public function isAccepted(): bool
    {
        return $this->validationStatus === ValidationStatus::Accepted && $this->simulationId !== null;
    }

    /**
     * @internal
     */
    public static function fromResponse(ApiResponse $response): self
    {
        $payload = $response->requirePayload();
        $rawStatus = $payload->string('validationStatus');

        return new self(
            validationStatus: ValidationStatus::fromApi($rawStatus),
            rawValidationStatus: $rawStatus,
            simulationId: $payload->string('simulationId'),
            transactionStatusDescription: $payload->string('transactionStatusDescription'),
            endToEndId: EndToEndId::normalize($payload->string('endToEndId')),
            amount: $payload->amount('amount'),
            currency: $payload->string('currency'),
            date: $payload->dateTime('date'),
            categoryPurpose: $payload->string('categoryPurpose'),
            paymentInformation: $payload->string('paymentInformation'),
            debtor: Party::fromPayload($payload, 'debtor'),
            creditor: Party::fromPayload($payload, 'creditor'),
            vop: VopResult::fromResponse($response),
            transactionId: $response->transactionId,
        );
    }

    /**
     * Serializable form, e.g. to store the simulation and execute the payment later (within 5 minutes).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'validationStatus' => $this->rawValidationStatus,
            'simulationId' => $this->simulationId,
            'transactionStatusDescription' => $this->transactionStatusDescription,
            'endToEndId' => $this->endToEndId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'date' => $this->date?->format(DATE_ATOM),
            'categoryPurpose' => $this->categoryPurpose,
            'paymentInformation' => $this->paymentInformation,
            'debtor' => $this->debtor->toArray(),
            'creditor' => $this->creditor->toArray(),
            'vop' => $this->vop->toArray(),
            'transactionId' => $this->transactionId,
        ];
    }

    /**
     * Rebuilds a simulation saved with toArray().
     *
     * @param  array<mixed>  $data
     *
     * @throws InvalidArgumentException When the data is not a saved simulation.
     */
    public static function fromArray(array $data): self
    {
        $text = static fn (string $key): ?string => is_string($data[$key] ?? null) ? $data[$key] : null;
        $array = static fn (string $key): array => is_array($data[$key] ?? null) ? $data[$key] : [];
        $date = $text('date');

        if (! array_key_exists('simulationId', $data) || ! array_key_exists('vop', $data)) {
            throw new InvalidArgumentException('The data is not a saved SCT Instant simulation.');
        }

        return new self(
            validationStatus: ValidationStatus::fromApi($text('validationStatus')),
            rawValidationStatus: $text('validationStatus'),
            simulationId: $text('simulationId'),
            transactionStatusDescription: $text('transactionStatusDescription'),
            endToEndId: $text('endToEndId'),
            amount: Decimal::fromSaved($data, 'amount'),
            currency: $text('currency'),
            date: $date === null ? null : DateParser::dateTime($date),
            categoryPurpose: $text('categoryPurpose'),
            paymentInformation: $text('paymentInformation'),
            debtor: Party::fromArray($array('debtor')),
            creditor: Party::fromArray($array('creditor')),
            vop: VopResult::fromArray($array('vop')),
            transactionId: $text('transactionId'),
        );
    }
}
