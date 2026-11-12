<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Response;

use Shellrent\OpenBanking\Support\Payload;

/**
 * Debtor or creditor of a payment, as returned by the API. Every field may be missing (docs/api-quirks.md, Q-22).
 */
final readonly class Party
{
    public function __construct(
        public ?string $name = null,
        public ?string $iban = null,
        public ?string $bic = null,
        public ?string $ultimateName = null,
        public ?string $nation = null,
        public ?string $address = null,
    ) {}

    /**
     * Reads the "{role}Name", "{role}IBAN"/"{role}Iban" (docs/api-quirks.md, Q-12), "{role}Bic",
     * "ultimate{Role}Name", "{role}Nation" and "{role}Address" fields.
     *
     * @internal
     *
     * @param  'debtor'|'creditor'  $role
     */
    public static function fromPayload(Payload $payload, string $role): self
    {
        return new self(
            name: $payload->string($role.'Name'),
            iban: $payload->string($role.'IBAN', $role.'Iban'),
            bic: $payload->string($role.'Bic', $role.'BIC'),
            ultimateName: $payload->string('ultimate'.ucfirst($role).'Name'),
            nation: $payload->string($role.'Nation'),
            address: $payload->string($role.'Address'),
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'iban' => $this->iban,
            'bic' => $this->bic,
            'ultimateName' => $this->ultimateName,
            'nation' => $this->nation,
            'address' => $this->address,
        ];
    }

    /**
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $text = static fn (string $key): ?string => is_string($data[$key] ?? null) ? $data[$key] : null;

        return new self($text('name'), $text('iban'), $text('bic'), $text('ultimateName'), $text('nation'), $text('address'));
    }
}
