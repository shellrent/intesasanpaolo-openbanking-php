<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Response\Accounts;

use DateTimeImmutable;
use Shellrent\OpenBanking\Support\DateFormat;
use Shellrent\OpenBanking\Support\Payload;

/**
 * Account balance (GET /accounts/{IBAN}/balance).
 *
 * Every value may be missing in the response (docs/api-quirks.md, Q-21).
 */
final readonly class Balance
{
    /**
     * @param  string|null  $currency  Currency of the account.
     * @param  float|null  $availableBalance  Today's available balance.
     * @param  float|null  $accountingBalance  Accounting balance on the requested date.
     * @param  float|null  $creditLine  Credit line of the account.
     * @param  DateTimeImmutable|null  $inquiryDate  Requested date (midnight, Europe/Rome).
     * @param  DateTimeImmutable|null  $executionDate  When the balance was extracted.
     * @param  string|null  $transactionId  "X-ISP-Transaction-ID" of the response, for the ISP support.
     */
    public function __construct(
        public ?string $currency,
        public ?float $availableBalance,
        public ?float $accountingBalance,
        public ?float $creditLine,
        public ?DateTimeImmutable $inquiryDate,
        public ?DateTimeImmutable $executionDate,
        public ?string $transactionId = null,
    ) {}

    /**
     * @internal
     */
    public static function fromPayload(Payload $payload): self
    {
        return new self(
            currency: $payload->string('currency'),
            availableBalance: $payload->float('availableBalance'),
            accountingBalance: $payload->float('accountingBalance'),
            creditLine: $payload->float('creditLine'),
            inquiryDate: $payload->date(DateFormat::Compact, 'inquiryDate'),
            executionDate: $payload->dateTime('executionDate'),
            transactionId: $payload->transactionId(),
        );
    }
}
