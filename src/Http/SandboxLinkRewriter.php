<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Http;

use Shellrent\OpenBanking\Config\ClientConfig;
use Shellrent\OpenBanking\Config\Environment;

/**
 * Fixes the pagination links of the sandbox, which point to unreachable hosts (docs/api-quirks.md, Q-44).
 *
 * In Live the links are used as they are.
 *
 * @internal
 */
final class SandboxLinkRewriter
{
    /**
     * Internal host used in the "nextPage" links of the account transactions.
     */
    private const string TRANSACTIONS_HOST = 'https://external-api.syssede.systest.sanpaoloimi.com';

    /**
     * Internal host used in the "links.next" links of the payment lists, followed by the path without the "/twa/sandbox" prefix.
     */
    private const string PAYMENT_LISTS_HOST = 'http://localhost:8081';

    /**
     * Fixed IBAN that the sandbox puts in the payment list links instead of the requested one.
     */
    private const string PLACEHOLDER_IBAN = 'IT59R0306901001100000002110';

    public function __construct(private readonly ClientConfig $config) {}

    public function rewrite(string $link): string
    {
        if ($this->config->environment !== Environment::Sandbox) {
            return $link;
        }

        if (str_starts_with($link, self::TRANSACTIONS_HOST)) {
            $link = ClientConfig::HOST.substr($link, strlen(self::TRANSACTIONS_HOST));
        }

        if (str_starts_with($link, self::PAYMENT_LISTS_HOST)) {
            $link = ClientConfig::HOST.'/twa/sandbox'.substr($link, strlen(self::PAYMENT_LISTS_HOST));
        }

        return str_replace('/'.self::PLACEHOLDER_IBAN.'/', '/'.$this->config->iban.'/', $link);
    }
}
