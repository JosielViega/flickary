<?php

declare(strict_types=1);

namespace Tests;

use App\Integrations\Tmdb\FileTmdbPayloadCache;
use App\Integrations\Tmdb\TmdbClient;
use PHPUnit\Framework\TestCase;

final class TmdbNavigationCacheIntegrationTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/flickary-navigation-cache-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->directory)) { return; }
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            if (is_file($file)) { unlink($file); }
        }
        rmdir($this->directory);
    }

    public function testDetailsAndSeasonAreCachedButSearchRemainsLive(): void
    {
        $calls = [];
        $http = static function (string $url) use (&$calls): array {
            $path = (string) parse_url($url, PHP_URL_PATH);
            $calls[$path] = ($calls[$path] ?? 0) + 1;
            $payload = match (true) {
                str_contains($path, '/season/1') => ['id' => 2, 'season_number' => 1, 'name' => 'Temporada 1', 'episodes' => []],
                str_contains($path, '/search/movie') => ['page' => 1, 'total_pages' => 1, 'total_results' => 1, 'results' => [[
                    'id' => 603, 'title' => 'Matrix', 'original_title' => 'The Matrix', 'release_date' => '1999-03-30',
                ]]],
                str_contains($path, '/tv/1396') => ['id' => 1396, 'name' => 'Breaking Bad', 'original_name' => 'Breaking Bad', 'first_air_date' => '2008-01-20'],
                default => ['id' => 603, 'title' => 'Matrix', 'original_title' => 'The Matrix', 'release_date' => '1999-03-30'],
            };
            return ['status' => 200, 'body' => json_encode($payload, JSON_THROW_ON_ERROR)];
        };
        $client = new TmdbClient(
            'test-token',
            http: $http,
            cache: new FileTmdbPayloadCache($this->directory),
        );

        $client->movieDetails(603);
        $client->movieDetails(603);
        $client->seriesDetails(1396);
        $client->seriesDetails(1396);
        $client->seasonDetails(1396, 1);
        $client->seasonDetails(1396, 1);
        $client->searchMovies('Matrix');
        $client->searchMovies('Matrix');

        self::assertSame(1, $calls['/3/movie/603']);
        self::assertSame(1, $calls['/3/tv/1396']);
        self::assertSame(1, $calls['/3/tv/1396/season/1']);
        self::assertSame(2, $calls['/3/search/movie']);
    }

    public function testExpiredDetailUsesValidStalePayloadOnTransientFailure(): void
    {
        $now = 1000;
        $calls = 0;
        $offline = false;
        $http = static function () use (&$calls, &$offline): array {
            $calls++;
            if ($offline) { return ['transport_error' => true]; }
            return ['status' => 200, 'body' => json_encode([
                'id' => 603, 'title' => 'Matrix', 'original_title' => 'The Matrix', 'release_date' => '1999-03-30',
            ], JSON_THROW_ON_ERROR)];
        };
        $cache = new FileTmdbPayloadCache($this->directory, now: static function () use (&$now): int { return $now; });
        $client = new TmdbClient('test-token', http: $http, cache: $cache);

        self::assertSame('Matrix', $client->movieDetails(603)->title);
        $now += 21601;
        $offline = true;
        self::assertSame('Matrix', $client->movieDetails(603)->title);
        self::assertSame(2, $calls);
    }
}
