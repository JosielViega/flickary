<?php

declare(strict_types=1);

namespace Tests;

use App\Integrations\Tmdb\TmdbClient;
use App\Integrations\Tmdb\TmdbException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TmdbClientTest extends TestCase
{
    private const CONFIGURATION_BODY = '{"images":{"secure_base_url":"https://image.tmdb.org/t/p/","poster_sizes":["w500"],"backdrop_sizes":["w1280"]}}';

    public function testUnconfiguredClientDoesNotCallTransport(): void
    {
        $called = false;
        $client = new TmdbClient('', http: static function () use (&$called): array {
            $called = true;
            return [];
        });

        self::assertFalse($client->configured());
        try {
            $client->configuration();
            self::fail('Expected exception.');
        } catch (TmdbException $exception) {
            self::assertSame('not_configured', $exception->category);
            self::assertFalse($called);
        }
    }

    public function testConfigurationUsesBearerAcceptHttpsAndTimeouts(): void
    {
        $call = [];
        $client = new TmdbClient(
            'secret-test-token',
            connectTimeout: 4,
            timeout: 9,
            maxResponseBytes: 12345,
            http: static function (string $url, array $headers, int $connectTimeout, int $timeout, int $maxBytes) use (&$call): array {
                $call = compact('url', 'headers', 'connectTimeout', 'timeout', 'maxBytes');
                return ['status' => 200, 'body' => self::CONFIGURATION_BODY];
            },
        );

        $configuration = $client->configuration();

        self::assertSame('https://api.themoviedb.org/3/configuration', $call['url']);
        self::assertContains('Accept: application/json', $call['headers']);
        self::assertContains('Authorization: Bearer secret-test-token', $call['headers']);
        self::assertSame(4, $call['connectTimeout']);
        self::assertSame(9, $call['timeout']);
        self::assertSame(12345, $call['maxBytes']);
        self::assertSame('https://image.tmdb.org/t/p/', $configuration->secureBaseUrl);
    }

    public function testSearchUsesRfc3986LocaleRegionAdultPolicyAndOnePage(): void
    {
        $urls = [];
        $http = static function (string $url) use (&$urls): array {
            $urls[] = $url;
            return ['status' => 200, 'body' => '{"page":2,"total_pages":3,"total_results":1,"results":[{"id":603,"title":"Matrix","release_date":"1999-03-30"}]}'];
        };
        $client = new TmdbClient('token', http: $http);

        $movies = $client->searchMovies('Matrix & Neo', 2);
        $client->searchSeries('Breaking Bad', 1);

        self::assertStringContainsString('query=Matrix%20%26%20Neo', $urls[0]);
        self::assertStringContainsString('language=pt-BR', $urls[0]);
        self::assertStringContainsString('include_adult=false', $urls[0]);
        self::assertStringContainsString('region=BR', $urls[0]);
        self::assertStringContainsString('page=2', $urls[0]);
        self::assertStringNotContainsString('region=', $urls[1]);
        self::assertSame(2, $movies['page']);
        self::assertSame('movie', $movies['results'][0]->mediaType);
    }

    /** @return iterable<string, array{int,string,?int}> */
    public static function httpErrors(): iterable
    {
        yield 'unauthorized' => [401, 'unauthorized', null];
        yield 'forbidden' => [403, 'forbidden', null];
        yield 'not found' => [404, 'not_found', null];
        yield 'rate limited' => [429, 'rate_limited', 120];
        yield 'server error' => [503, 'server_error', null];
    }

    #[DataProvider('httpErrors')]
    public function testClassifiesHttpErrors(int $status, string $category, ?int $retryAfter): void
    {
        $client = new TmdbClient('token', http: static fn (): array => [
            'status' => $status,
            'body' => '{}',
            'headers' => ['retry-after' => '120'],
        ]);

        try {
            $client->configuration();
            self::fail('Expected exception.');
        } catch (TmdbException $exception) {
            self::assertSame($category, $exception->category);
            self::assertSame($status, $exception->httpStatus);
            self::assertSame($retryAfter, $exception->retryAfter);
        }
    }

    public function testRejectsInvalidJsonUnexpectedPayloadAndOversizedResponse(): void
    {
        $invalidJson = new TmdbClient('token', http: static fn (): array => ['status' => 200, 'body' => '{']);
        $unexpected = new TmdbClient('token', http: static fn (): array => ['status' => 200, 'body' => '[]']);
        $oversized = new TmdbClient('token', maxResponseBytes: 4, http: static fn (): array => ['status' => 200, 'body' => '{"ok":true}']);

        self::assertSame('invalid_json', $this->categoryFrom($invalidJson));
        self::assertSame('unexpected_payload', $this->categoryFrom($unexpected));
        self::assertSame('response_too_large', $this->categoryFrom($oversized));
    }

    public function testClassifiesTransportFailureAndNeverLeaksToken(): void
    {
        $token = 'token-that-must-not-leak';
        $client = new TmdbClient($token, http: static function (): array {
            throw new \RuntimeException('low-level failure');
        });

        try {
            $client->configuration();
            self::fail('Expected exception.');
        } catch (TmdbException $exception) {
            self::assertSame('transport', $exception->category);
            self::assertStringNotContainsString($token, (string) $exception);
        }
    }

    public function testRejectsUnexpectedSearchPayloadAndInvalidPage(): void
    {
        $client = new TmdbClient('token', http: static fn (): array => ['status' => 200, 'body' => '{"results":[]}']);
        self::assertSame('unexpected_payload', $this->categoryFrom($client, true));

        $this->expectException(\InvalidArgumentException::class);
        $client->searchMovies('Matrix', 0);
    }

    private function categoryFrom(TmdbClient $client, bool $search = false): string
    {
        try {
            $search ? $client->searchMovies('Matrix') : $client->configuration();
            self::fail('Expected exception.');
        } catch (TmdbException $exception) {
            return $exception->category;
        }
    }
}
