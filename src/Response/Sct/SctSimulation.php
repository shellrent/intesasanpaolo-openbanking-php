<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Response\Sct;

use DateTimeImmutable;
use InvalidArgumentException;
use Shellrent\OpenBanking\Enum\ValidationStatus;
use Shellrent\OpenBanking\Http\ApiResponse;
use Shellrent\OpenBanking\Response\Party;
use Shellrent\OpenBanking\Response\VopResult;
use Shellrent\OpenBanking\Support\DateFormat;
use Shellrent\OpenBanking\Support\DateParser;
use Shellrent\OpenBanking\Support\EndToEndId;

/**
 * Result of an SCT simulation (POST /payments/bonsct/simulation).
 *
 * The simulation ID is valid for 5 minutes (docs/api-quirks.md, Q-33). To execute the payment in another
 * process, save the simulation with toArray() and restore it with fromArray().
 */
final readonly class SctSimulation
{
    /**
     * @param  ValidationStatus|null  $validationStatus  Outcome, or null for an unknown value: see $rawValidationStatus.
     * @param  float|null  $fees  Fees of the payment ("fees", or "commissioni" in older responses: docs/api-quirks.md, Q-15).
     * @param  DateTimeImmutable|null  $executionDate  Planned execution date.
     * @param  DateTimeImmutable|null  $valueDate  Planned value date.
     * @param  DateTimeImmutable|null  $settlementDate  Planned settlement date.
     * @param  VopResult  $vop  Verification of Payee: executing the payment counts as accepting it.
     * @param  string|null  $transactionId  "X-ISP-Transaction-ID" of the response, for the ISP support.
     */
    public function __construct(
        public ?ValidationStatus $validationStatus,
        public ?string $rawValidationStatus,
        public ?string $simulationId,
        public ?string $transactionStatusDescription,
        public ?string $endToEndId,
        public ?float $amount,
        public ?string $currency,
        public ?string $categoryPurpose,
        public ?string $paymentInformation,
        public Party $debtor,
        public Party $creditor,
        public ?string $creditorAgent,
        public ?float $fees,
        public ?DateTimeImmutable $executionDate,
        public ?DateTimeImmutable $valueDate,
        public ?DateTimeImmutable $settlementDate,
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
            amount: $payload->float('amount'),
            currency: $payload->string('currency'),
            categoryPurpose: $payload->string('categoryPurpose'),
            paymentInformation: $payload->string('paymentInformation'),
            debtor: Party::fromPayload($payload, 'debtor'),
            creditor: Party::fromPayload($payload, 'creditor'),
            creditorAgent: $payload->string('creditorAgent'),
            fees: $payload->float('fees', 'commissioni'),
            executionDate: SctDates::read($payload, 'executionDate'),
            valueDate: SctDates::read($payload, 'valueDate'),
            settlementDate: SctDates::read($payload, 'settlementDate'),
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
            'categoryPurpose' => $this->categoryPurpose,
            'paymentInformation' => $this->paymentInformation,
            'debtor' => $this->debtor->toArray(),
            'creditor' => $this->creditor->toArray(),
            'creditorAgent' => $this->creditorAgent,
            'fees' => $this->fees,
            'executionDate' => SctDates::format($this->executionDate),
            'valueDate' => SctDates::format($this->valueDate),
            'settlementDate' => SctDates::format($this->settlementDate),
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
        if (! array_key_exists('simulationId', $data) || ! array_key_exists('vop', $data)) {
            throw new InvalidArgumentException('The data is not a saved SCT simulation.');
        }

        $text = static fn (string $key): ?string => is_string($data[$key] ?? null) ? $data[$key] : null;
        $number = static fn (string $key): ?float => is_int($data[$key] ?? null) || is_float($data[$key] ?? null) ? (float) $data[$key] : null;
        $array = static fn (string $key): array => is_array($data[$key] ?? null) ? $data[$key] : [];
        $date = static fn (string $key): ?DateTimeImmutable => $text($key) === null ? null : DateParser::date((string) $text($key), DateFormat::Compact);

        return new self(
            validationStatus: ValidationStatus::fromApi($text('validationStatus')),
            rawValidationStatus: $text('validationStatus'),
            simulationId: $text('simulationId'),
            transactionStatusDescription: $text('transactionStatusDescription'),
            endToEndId: $text('endToEndId'),
            amount: $number('amount'),
            currency: $text('currency'),
            categoryPurpose: $text('categoryPurpose'),
            paymentInformation: $text('paymentInformation'),
            debtor: Party::fromArray($array('debtor')),
            creditor: Party::fromArray($array('creditor')),
            creditorAgent: $text('creditorAgent'),
            fees: $number('fees'),
            executionDate: $date('executionDate'),
            valueDate: $date('valueDate'),
            settlementDate: $date('settlementDate'),
            vop: VopResult::fromArray($array('vop')),
            transactionId: $text('transactionId'),
        );
    }
}
