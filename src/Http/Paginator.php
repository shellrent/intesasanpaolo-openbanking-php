<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Http;

use Closure;
use Generator;
use Shellrent\OpenBanking\Exception\UnexpectedResponseException;

/**
 * Follows the "next page" links of paginated APIs (docs/api-quirks.md, Q-41 to Q-44).
 *
 * Each API knows where its link is: the caller passes a closure that extracts it from a page.
 * The paginator rewrites the broken sandbox links and stops with an exception, instead of looping
 * forever, when a link repeats or the number of pages exceeds the limit.
 *
 * @internal
 */
final class Paginator
{
    public const int DEFAULT_MAX_PAGES = 100;

    public function __construct(
        private readonly Transport $transport,
        private readonly SandboxLinkRewriter $rewriter,
        private readonly int $maxPages = self::DEFAULT_MAX_PAGES,
    ) {}

    /**
     * @param  array<string, scalar>  $query  Query of the first page; the following pages use the query of their link.
     * @param  Closure(ApiResponse): ?string  $nextLink  Returns the link to the next page, or null on the last page.
     * @param  array<string, string>  $headers  Additional request headers, sent on every page.
     * @return Generator<int, ApiResponse, mixed, void>
     *
     * @throws UnexpectedResponseException When a link repeats or there are more than the maximum pages.
     *
     * @phpstan-impure
     */
    public function pages(string $path, array $query, Closure $nextLink, array $headers = []): Generator
    {
        $response = $this->transport->get($path, $query, $headers);
        $seen = [];
        $count = 1;

        yield $response;

        while (($link = $nextLink($response)) !== null && $link !== '') {
            $link = $this->rewriter->rewrite($link);

            if (isset($seen[$link])) {
                throw new UnexpectedResponseException(
                    sprintf('Pagination loop: the link "%s" was already followed.', $link),
                    $response->transactionId,
                );
            }

            if (++$count > $this->maxPages) {
                throw new UnexpectedResponseException(
                    sprintf('Pagination stopped: more than %d pages.', $this->maxPages),
                    $response->transactionId,
                );
            }

            $seen[$link] = true;
            $response = $this->transport->get($link, [], $headers);

            yield $response;
        }
    }
}
