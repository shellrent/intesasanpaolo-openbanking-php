<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Shellrent\OpenBanking\Api\AccountsApi;
use Shellrent\OpenBanking\Api\IbanToolsApi;
use Shellrent\OpenBanking\Api\SctApi;
use Shellrent\OpenBanking\Api\SctInstantApi;
use Shellrent\OpenBanking\Auth\InMemoryTokenStore;
use Shellrent\OpenBanking\Auth\TokenProvider;
use Shellrent\OpenBanking\Auth\TokenStore;
use Shellrent\OpenBanking\Config\ClientConfig;
use Shellrent\OpenBanking\Http\Paginator;
use Shellrent\OpenBanking\Http\SandboxLinkRewriter;
use Shellrent\OpenBanking\Http\Sender;
use Shellrent\OpenBanking\Http\Transport;

/**
 * Entry point of the SDK for the Intesa Sanpaolo SmartHub APIs.
 *
 * ```php
 * $client = new IntesaSanPaoloClient(new ClientConfig(...));
 * $balance = $client->accounts()->balance();
 * $simulation = $client->sctInstant()->simulate($payment);
 * ```
 */
final class IntesaSanPaoloClient
{
    private readonly Transport $transport;

    private readonly Paginator $paginator;

    private ?AccountsApi $accounts = null;

    private ?SctInstantApi $sctInstant = null;

    private ?SctApi $sct = null;

    private ?IbanToolsApi $ibanTools = null;

    /**
     * @param  ClientInterface|null  $httpClient  Guzzle client to use, e.g. with logging middleware; certificate
     *                                            and timeouts of the configuration are applied to every request.
     * @param  TokenStore  $tokenStore  Where the OAuth2 token is kept; share it to reuse the token across instances.
     */
    public function __construct(
        private readonly ClientConfig $config,
        ?ClientInterface $httpClient = null,
        TokenStore $tokenStore = new InMemoryTokenStore,
    ) {
        $sender = new Sender($config, $httpClient ?? new Client);

        $this->transport = new Transport($config, $sender, new TokenProvider($config, $sender, $tokenStore));
        $this->paginator = new Paginator($this->transport, new SandboxLinkRewriter($config));
    }

    public function config(): ClientConfig
    {
        return $this->config;
    }

    /**
     * Balance and transactions of the configured account.
     */
    public function accounts(): AccountsApi
    {
        return $this->accounts ??= new AccountsApi($this->config, $this->transport, $this->paginator);
    }

    /**
     * SCT Instant payments (bonifico istantaneo): simulation, execution, resubmission, status and list.
     */
    public function sctInstant(): SctInstantApi
    {
        return $this->sctInstant ??= new SctInstantApi($this->config, $this->transport, $this->paginator);
    }

    /**
     * SCT payments (bonifico ordinario): simulation, execution, status, list and revoke.
     */
    public function sct(): SctApi
    {
        return $this->sct ??= new SctApi($this->config, $this->transport, $this->paginator);
    }

    /**
     * IBAN checks: formal check, existence and consistency with the holder's fiscal code or VAT number.
     */
    public function ibanTools(): IbanToolsApi
    {
        return $this->ibanTools ??= new IbanToolsApi($this->transport);
    }
}
