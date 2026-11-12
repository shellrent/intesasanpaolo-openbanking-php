<?php

declare(strict_types=1);

namespace Shellrent\OpenBanking\Tests\Support;

use ArrayObject;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Guzzle client backed by a MockHandler, which records every request it receives.
 */
final class MockHttp
{
    public readonly Client $client;

    /**
     * @var ArrayObject<int, array{request: RequestInterface, response: ResponseInterface|null, error: mixed, options: array<mixed>}>
     */
    private ArrayObject $history;

    private readonly MockHandler $handler;

    public function __construct(ResponseInterface|Throwable ...$queue)
    {
        $this->history = new ArrayObject;
        $this->handler = new MockHandler(array_values($queue));

        $history = $this->history;
        $stack = HandlerStack::create($this->handler);
        $stack->push(Middleware::history($history));

        $this->client = new Client(['handler' => $stack]);
    }

    public function append(ResponseInterface|Throwable ...$queue): void
    {
        $this->handler->append(...$queue);
    }

    public function count(): int
    {
        return count($this->history);
    }

    public function request(int $index): RequestInterface
    {
        return $this->recorded($index)['request'];
    }

    /**
     * Guzzle options the request was sent with.
     *
     * @return array<mixed>
     */
    public function options(int $index): array
    {
        return $this->recorded($index)['options'];
    }

    /**
     * JSON body of a recorded request, decoded as an associative array.
     *
     * @return array<mixed>
     */
    public function jsonBody(int $index): array
    {
        $body = json_decode((string) $this->request($index)->getBody(), true, 512, JSON_THROW_ON_ERROR);
        Assert::assertIsArray($body);

        return $body;
    }

    /**
     * @param  array<mixed>  $body
     * @param  array<string, string>  $headers
     */
    public static function json(array $body, int $status = 200, array $headers = []): Response
    {
        return new Response($status, ['Content-Type' => 'application/json', ...$headers], json_encode($body, JSON_THROW_ON_ERROR));
    }

    /**
     * Successful API response with the standard envelope.
     *
     * @param  array<mixed>|null  $payload
     * @param  array<string, string>  $headers
     */
    public static function envelope(?array $payload, array $headers = []): Response
    {
        return self::json(['success' => true, 'errors' => null, 'payload' => $payload === null ? null : (object) $payload], 200, $headers);
    }

    /**
     * Response with the body of a fixture file, e.g. fixture('Accounts/balance') reads tests/Fixtures/Accounts/balance.json.
     *
     * @param  array<string, string>  $headers
     */
    public static function fixture(string $name, array $headers = [], int $status = 200): Response
    {
        $body = file_get_contents(__DIR__.'/../Fixtures/'.$name.'.json');
        Assert::assertIsString($body, "Fixture {$name} not found");

        return new Response($status, ['Content-Type' => 'application/json', ...$headers], $body);
    }

    public static function token(string $value = 'token-123', int $expiresIn = 3600): Response
    {
        return self::json(['access_token' => $value, 'token_type' => 'Bearer', 'expires_in' => $expiresIn]);
    }

    /**
     * @return array{request: RequestInterface, response: ResponseInterface|null, error: mixed, options: array<mixed>}
     */
    private function recorded(int $index): array
    {
        $entry = $this->history[$index] ?? null;
        Assert::assertNotNull($entry, "No request was recorded at index {$index}");

        return $entry;
    }
}
