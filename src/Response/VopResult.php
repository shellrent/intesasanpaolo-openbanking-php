<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Response;

use Shellrent\OpenBanking\Enum\VopMatchResult;
use Shellrent\OpenBanking\Http\ApiResponse;

/**
 * Verification of Payee of a simulated payment, read from the response headers
 * "vopMatchResult", "vopMatchedName" and "vopResultDescription" (docs/api-quirks.md, Q-37).
 *
 * Executing the payment counts as accepting this outcome.
 */
final readonly class VopResult
{
    /**
     * @param  VopMatchResult|null  $result  Outcome, or null when the API sent none or an unknown value: see $rawResult.
     * @param  string|null  $rawResult  Value of the "vopMatchResult" header, exactly as sent.
     * @param  string|null  $matchedName  Name registered at the creditor bank, suggested on a close match.
     * @param  string|null  $description  Details of a failed verification.
     */
    public function __construct(
        public ?VopMatchResult $result,
        public ?string $rawResult,
        public ?string $matchedName = null,
        public ?string $description = null,
    ) {}

    /**
     * @internal
     */
    public static function fromResponse(ApiResponse $response): self
    {
        $raw = $response->header('vopMatchResult');

        return new self(
            VopMatchResult::fromApi($raw),
            $raw,
            $response->header('vopMatchedName'),
            $response->header('vopResultDescription'),
        );
    }

    /**
     * Whether the bank sent a verification outcome at all.
     */
    public function wasPerformed(): bool
    {
        return $this->rawResult !== null;
    }

    public function isMatch(): bool
    {
        return $this->result === VopMatchResult::Match;
    }

    /**
     * Anything but a full match (including a missing or unknown outcome) needs an explicit decision.
     */
    public function requiresConfirmation(): bool
    {
        return ! $this->isMatch();
    }

    /**
     * Short text for logs and messages, e.g. "CMTC, suggested name: Mario Rossi".
     */
    public function summary(): string
    {
        if (! $this->wasPerformed()) {
            return 'no verification outcome in the response';
        }

        $parts = [$this->rawResult];

        if ($this->matchedName !== null) {
            $parts[] = 'suggested name: '.$this->matchedName;
        }

        if ($this->description !== null) {
            $parts[] = $this->description;
        }

        return implode(', ', $parts);
    }

    /**
     * @return array{rawResult: string|null, matchedName: string|null, description: string|null}
     */
    public function toArray(): array
    {
        return ['rawResult' => $this->rawResult, 'matchedName' => $this->matchedName, 'description' => $this->description];
    }

    /**
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $text = static fn (string $key): ?string => is_string($data[$key] ?? null) ? $data[$key] : null;

        return new self(VopMatchResult::fromApi($text('rawResult')), $text('rawResult'), $text('matchedName'), $text('description'));
    }
}
