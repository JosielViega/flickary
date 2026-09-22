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

    public function testAnimeDiscoverUsesRestrictedEndpointsAndOfficialFilters(): void
    {
        $urls = [];
        $client = new TmdbClient('token', http: static function (string $url) use (&$urls): array {
            $urls[] = $url;
            $item = str_contains($url, '/discover/movie')
                ? '{"id":129,"title":"A Viagem de Chihiro","genre_ids":[16,10751],"original_language":"ja"}'
                : '{"id":46260,"name":"Naruto","genre_ids":[16,10759],"original_language":"ja"}';
            return ['status'=>200,'body'=>'{"page":2,"total_pages":30,"total_results":600,"results":['.$item.']}'];
        });

        $movies = $client->discoverAnimeMovies(2);
        $series = $client->discoverAnimeSeries(2);

        self::assertStringStartsWith('https://api.themoviedb.org/3/discover/movie?', $urls[0]);
        self::assertStringStartsWith('https://api.themoviedb.org/3/discover/tv?', $urls[1]);
        foreach ($urls as $url) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            self::assertSame('pt-BR', $query['language']);
            self::assertSame('false', $query['include_adult']);
            self::assertSame('2', $query['page']);
            self::assertSame('16', $query['with_genres']);
            self::assertSame('ja', $query['with_original_language']);
            self::assertSame('popularity.desc', $query['sort_by']);
            self::assertArrayNotHasKey('region', $query);
            self::assertArrayNotHasKey('with_origin_country', $query);
            self::assertArrayNotHasKey('with_keywords', $query);
        }
        self::assertSame('movie', $movies['results'][0]->mediaType);
        self::assertSame('series', $series['results'][0]->mediaType);
        self::assertNotSame('anime', $series['results'][0]->mediaType);
    }

    public function testDiscoverRejectsInvalidPagesBeforeTransport(): void
    {
        $called = false;
        $client = new TmdbClient('token', http: static function () use (&$called): array { $called=true; return []; });
        foreach ([0, -1, 501] as $page) {
            try { $client->discoverAnimeMovies($page); self::fail('Expected invalid page.'); } catch (\InvalidArgumentException) {}
        }
        self::assertFalse($called);
    }

    public function testWhitelistAllowsOnlyConcreteDiscoverPaths(): void
    {
        $method = new \ReflectionMethod(TmdbClient::class, 'isSupportedPath');
        $client = new TmdbClient('token');
        self::assertTrue($method->invoke($client, '/discover/movie'));
        self::assertTrue($method->invoke($client, '/discover/tv'));
        foreach (['/discover/person','/discover/qualquer','https://attacker.example/discover/movie','/discover/movie/extra'] as $path) {
            self::assertFalse($method->invoke($client, $path));
        }
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

    public function testMovieAndSeriesDetailsUseRestrictedPathsAndLanguageOnly(): void
    {
        $urls = [];
        $client = new TmdbClient('token', http: static function (string $url) use (&$urls): array {
            $urls[] = $url;
            $body = str_contains($url, '/movie/')
                ? '{"id":603,"title":"Matrix","original_title":"The Matrix","release_date":"1999-03-31","genres":[{"id":28,"name":"Ação"}],"runtime":136,"tagline":"Realidade.","poster_path":"/poster.jpg","backdrop_path":"/backdrop.jpg","vote_average":8.2,"adult":false}'
                : '{"id":1396,"name":"Breaking Bad","original_name":"Breaking Bad","first_air_date":"2008-01-20","genres":[{"id":18,"name":"Drama"}],"number_of_seasons":5,"number_of_episodes":62,"tagline":"Change.","poster_path":"/tv.jpg","backdrop_path":"/tv-backdrop.jpg","adult":false}';
            return ['status' => 200, 'body' => $body];
        });

        $movie = $client->movieDetails(603);
        $series = $client->seriesDetails(1396);

        self::assertSame('https://api.themoviedb.org/3/movie/603?language=pt-BR', $urls[0]);
        self::assertSame('https://api.themoviedb.org/3/tv/1396?language=pt-BR', $urls[1]);
        self::assertStringNotContainsString('region=', implode(' ', $urls));
        self::assertStringNotContainsString('append_to_response', implode(' ', $urls));
        self::assertSame('movie', $movie->mediaType);
        self::assertSame(136, $movie->runtime);
        self::assertSame('series', $series->mediaType);
        self::assertSame(5, $series->numberOfSeasons);
        self::assertSame(62, $series->numberOfEpisodes);
    }

    /** @return iterable<string, array{int}> */
    public static function invalidDetailIds(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'outside int32' => [2147483648];
    }

    #[DataProvider('invalidDetailIds')]
    public function testDetailsRejectInvalidIdsBeforeTransport(int $id): void
    {
        $called = false;
        $client = new TmdbClient('token', http: static function () use (&$called): array {
            $called = true;
            return ['status' => 200, 'body' => '{}'];
        });

        try {
            $client->movieDetails($id);
            self::fail('Expected invalid ID exception.');
        } catch (\InvalidArgumentException) {
            self::assertFalse($called);
        }
    }

    public function testDetailsRejectUnexpectedPayloadAndMapNotFound(): void
    {
        $unexpected = new TmdbClient('token', http: static fn (): array => [
            'status' => 200,
            'body' => '{"id":603,"title":""}',
        ]);
        $notFound = new TmdbClient('token', http: static fn (): array => [
            'status' => 404,
            'body' => '{}',
        ]);

        try {
            $unexpected->movieDetails(603);
            self::fail('Expected unexpected payload.');
        } catch (TmdbException $exception) {
            self::assertSame('unexpected_payload', $exception->category);
        }

        try {
            $notFound->seriesDetails(1396);
            self::fail('Expected not found.');
        } catch (TmdbException $exception) {
            self::assertSame('not_found', $exception->category);
            self::assertSame(404, $exception->httpStatus);
        }
    }

    public function testRejectsArbitraryApiHost(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TmdbClient('token', 'https://attacker.example/3');
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
