<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Api;

use DateTimeInterface;
use Shellrent\OpenBanking\Config\ClientConfig;
use Shellrent\OpenBanking\Exception\ApiException;
use Shellrent\OpenBanking\Exception\AuthenticationException;
use Shellrent\OpenBanking\Exception\TransportException;
use Shellrent\OpenBanking\Exception\UnexpectedResponseException;
use Shellrent\OpenBanking\Http\ApiResponse;
use Shellrent\OpenBanking\Http\Paginator;
use Shellrent\OpenBanking\Http\Transport;
use Shellrent\OpenBanking\Response\Accounts\Balance;
use Shellrent\OpenBanking\Response\Accounts\TransactionList;
use Shellrent\OpenBanking\Support\DateFormat;
use Shellrent\OpenBanking\Support\DateParser;
use Shellrent\OpenBanking\Support\Url;

/**
 * Account information: balance and transactions of the configured IBAN.
 *
 * Obtain it with IntesaSanPaoloClient::accounts().
 */
final class AccountsApi
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ClientConfig $config,
        private readonly Transport $transport,
        private readonly Paginator $paginator,
    ) {}

    /**
     * Balance of the account, today or on a past date (up to 18 months).
     *
     * @param  DateTimeInterface|null  $date  Date of the accounting balance; null for today.
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedResponseException
     */
    public function balance(?DateTimeInterface $date = null): Balance
    {
        $query = $date === null ? [] : ['date' => DateParser::format($date, DateFormat::Compact)];

        return Balance::fromPayload($this->transport->get($this->path('balance'), $query)->requirePayload());
    }

    /**
     * End-of-day transactions of a past day (from yesterday back to 18 months), all pages included.
     *
     * Check TransactionList::isComplete() before treating the result as the full day.
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedResponseException
     */
    public function transactions(DateTimeInterface $date): TransactionList
    {
        $day = DateParser::format($date, DateFormat::Compact);

        return $this->collect(
            $this->path('transactions'),
            ['date' => $day],
            static fn (ApiResponse $page): ?string => Url::withQueryValue($page->requirePayload()->string('nextPage'), 'date', $day),
        );
    }

    /**
     * Today's transactions (intraday), accounted and not yet accounted, all pages included.
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedResponseException
     */
    public function todayTransactions(): TransactionList
    {
        return $this->collect(
            $this->path('transactions/today'),
            [],
            static fn (ApiResponse $page): ?string => $page->requirePayload()->string('nextPage'),
        );
    }

    /**
     * @param  array<string, scalar>  $query
     * @param  \Closure(ApiResponse): ?string  $nextLink
     */
    private function collect(string $path, array $query, \Closure $nextLink): TransactionList
    {
        $pages = [];

        foreach ($this->paginator->pages($path, $query, $nextLink) as $page) {
            $pages[] = $page->requirePayload();
        }

        if ($pages === []) {
            throw new UnexpectedResponseException('The transactions request returned no pages.');
        }

        return TransactionList::fromPages($pages);
    }

    private function path(string $resource): string
    {
        return sprintf('accounts/%s/%s', rawurlencode($this->config->iban), $resource);
    }
}
