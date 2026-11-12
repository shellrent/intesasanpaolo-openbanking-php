<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Response\Accounts;

use DateTimeImmutable;
use Shellrent\OpenBanking\Enum\TransactionStatus;
use Shellrent\OpenBanking\Support\DateFormat;
use Shellrent\OpenBanking\Support\Payload;

/**
 * Account transaction (movimento), accounted or not yet accounted.
 *
 * Unaccounted transactions (intraday only) have no unique ID, date, accounting date or
 * additional info (docs/api-quirks.md, Q-23).
 */
final readonly class Transaction
{
    /**
     * @param  string|null  $uniqueId  Unique identifier: present for accounted transactions only.
     * @param  string|null  $reference  Accounting reference.
     * @param  float  $amount  Signed amount: negative for debits.
     * @param  float|null  $originalAmount  Original amount, for accounts held at other banks.
     * @param  string|null  $type  Short description, e.g. the transaction category code.
     * @param  string|null  $description  Extended description.
     * @param  string|null  $additionalInfo  Additional information (in 2.x: getReason()).
     * @param  DateTimeImmutable|null  $date  Registration date.
     * @param  DateTimeImmutable|null  $valueDate  Value date; null when the API sends none, "" or "0".
     * @param  TransactionStatus|null  $status  Status, or null when the value is unknown: see $rawStatus.
     * @param  string|null  $rawStatus  Status exactly as sent by the API.
     */
    public function __construct(
        public ?string $uniqueId,
        public ?string $reference,
        public float $amount,
        public ?string $currency,
        public ?float $originalAmount,
        public ?string $originalCurrency,
        public ?float $exchangeRate,
        public ?string $type,
        public ?string $description,
        public ?string $additionalInfo,
        public ?DateTimeImmutable $date,
        public ?DateTimeImmutable $accountingDate,
        public ?DateTimeImmutable $valueDate,
        public ?TransactionStatus $status,
        public ?string $rawStatus,
    ) {}

    /**
     * @internal
     */
    public static function fromPayload(Payload $payload): self
    {
        $rawStatus = $payload->string('status');

        return new self(
            uniqueId: $payload->string('uniqueId'),
            reference: $payload->string('reference'),
            amount: $payload->requireFloat('amount'),
            currency: $payload->string('currency'),
            originalAmount: $payload->float('originalAmount'),
            originalCurrency: $payload->string('originalCurrency'),
            exchangeRate: $payload->float('exchangeRate'),
            type: $payload->string('type'),
            description: $payload->string('description'),
            additionalInfo: $payload->string('additionalInfo'),
            date: $payload->date(DateFormat::Compact, 'date'),
            accountingDate: $payload->date(DateFormat::Compact, 'accountingDate'),
            valueDate: $payload->date(DateFormat::Compact, 'valueDate'),
            status: TransactionStatus::fromApi($rawStatus),
            rawStatus: $rawStatus,
        );
    }
}
