<?php

declare(strict_types=1);

namespace Tests;

use App\Discovery\MediaCompanionShelfService;
use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbDiscoveryCatalog;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageConfiguration;
use App\Integrations\Tmdb\TmdbMedia;
use App\Integrations\Tmdb\TmdbMediaDetails;
use App\Integrations\Tmdb\TmdbSeasonDetails;
use PHPUnit\Framework\TestCase;

final class MediaCompanionShelfServiceTest extends TestCase
{
    public function testFiltersCurrentAdultAndDuplicatesAndLimitsShelf(): void
    {
        $catalog = new CompanionCatalogFake();
        $catalog->movies = [
            $this->media(603),
            $this->media(604, true),
            $this->media(605),
            $this->media(605),
            ...array_map(fn (int $id): TmdbMedia => $this->media($id), range(606, 620)),
        ];

        $items = (new MediaCompanionShelfService($catalog, $catalog))->forDetails($this->details('movie', 603), false);

        self::assertCount(10, $items);
        self::assertSame(605, $items[0]->sourceId);
        self::assertNotContains(603, array_column($items, 'sourceId'));
        self::assertSame(['trendingMovies'], $catalog->calls);
    }

    public function testUsesAnimeCollectionAndFailsSoftly(): void
    {
        $catalog = new CompanionCatalogFake();
        $catalog->animeSeries = [$this->media(2, false, 'series')];
        $service = new MediaCompanionShelfService($catalog, $catalog);

        self::assertSame(2, $service->forDetails($this->details('series', 1), true)[0]->sourceId);
        self::assertSame(['discoverAnimeSeries'], $catalog->calls);

        $catalog->fail = true;
        self::assertSame([], $service->forDetails($this->details('movie', 1), false));
    }

    private function media(int $id, bool $adult = false, string $type = 'movie'): TmdbMedia
    {
        return new TmdbMedia('tmdb', $type, $id, 'Title ' . $id, null, null, '2026-01-01', 2026, '/p.jpg', null, 8.0, 100, 1.0, [], 'en', $adult);
    }

    private function details(string $type, int $id): TmdbMediaDetails
    {
        return new TmdbMediaDetails('tmdb', $type, $id, 'Current', null, null, null, null, null, null, null, [], null, null, null, 'en', false, null, null, null, null, null, null);
    }
}

final class CompanionCatalogFake implements TmdbCatalog, TmdbDiscoveryCatalog
{
    /** @var list<TmdbMedia> */ public array $movies = [];
    /** @var list<TmdbMedia> */ public array $series = [];
    /** @var list<TmdbMedia> */ public array $animeMovies = [];
    /** @var list<TmdbMedia> */ public array $animeSeries = [];
    /** @var list<string> */ public array $calls = [];
    public bool $fail = false;

    public function configured(): bool { return true; }
    public function configuration(): TmdbImageConfiguration { throw new \LogicException(); }
    public function movieDetails(int $id): TmdbMediaDetails { throw new \LogicException(); }
    public function seriesDetails(int $id): TmdbMediaDetails { throw new \LogicException(); }
    public function seasonDetails(int $seriesId, int $seasonNumber): TmdbSeasonDetails { throw new \LogicException(); }
    public function searchMovies(string $query, int $page = 1): array { return []; }
    public function searchSeries(string $query, int $page = 1): array { return []; }
    public function discoverAnimeMovies(int $page = 1): array { $this->calls[] = 'discoverAnimeMovies'; return $this->result($this->animeMovies); }
    public function discoverAnimeSeries(int $page = 1): array { $this->calls[] = 'discoverAnimeSeries'; return $this->result($this->animeSeries); }
    public function trendingAll(): array { return $this->result([]); }
    public function trendingMovies(): array { $this->calls[] = 'trendingMovies'; return $this->result($this->movies); }
    public function trendingSeries(): array { $this->calls[] = 'trendingSeries'; return $this->result($this->series); }
    public function nowPlayingMovies(): array { return $this->result([]); }
    public function upcomingMovies(): array { return $this->result([]); }
    public function popularMovies(): array { return $this->result([]); }
    public function popularSeries(): array { return $this->result([]); }
    public function topRatedMovies(): array { return $this->result([]); }
    public function topRatedSeries(): array { return $this->result([]); }
    public function bestMoviesOfYear(int $year): array { return $this->result([]); }
    public function bestSeriesOfYear(int $year): array { return $this->result([]); }
    public function bestAnimeMoviesOfYear(int $year): array { return $this->result([]); }
    public function bestAnimeSeriesOfYear(int $year): array { return $this->result([]); }

    /** @param list<TmdbMedia> $items */
    private function result(array $items): array
    {
        if ($this->fail) { throw new TmdbException('transport'); }
        return ['page' => 1, 'total_pages' => 1, 'total_results' => count($items), 'results' => $items];
    }
}
