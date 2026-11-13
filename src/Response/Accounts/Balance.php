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
     * @param  string|null  $availableBalance  Today's available balance, as a decimal string (e.g. "14980.10", D-65).
     * @param  string|null  $accountingBalance  Accounting balance on the requested date.
     * @param  string|null  $creditLine  Credit line of the account.
     * @param  DateTimeImmutable|null  $inquiryDate  Requested date (midnight, Europe/Rome).
     * @param  DateTimeImmutable|null  $executionDate  When the balance was extracted.
     * @param  string|null  $transactionId  "X-ISP-Transaction-ID" of the response, for the ISP support.
     */
    public function __construct(
        public ?string $currency,
        public ?string $availableBalance,
        public ?string $accountingBalance,
        public ?string $creditLine,
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
            availableBalance: $payload->amount('availableBalance'),
            accountingBalance: $payload->amount('accountingBalance'),
            creditLine: $payload->amount('creditLine'),
            inquiryDate: $payload->date(DateFormat::Compact, 'inquiryDate'),
            executionDate: $payload->dateTime('executionDate'),
            transactionId: $payload->transactionId(),
        );
    }
}
