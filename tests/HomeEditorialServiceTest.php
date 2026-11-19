<?php

declare(strict_types=1);

namespace Tests;

use App\Home\HomeEditorialService;
use App\Home\HomeImageService;
use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbDiscoveryCatalog;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageConfiguration;
use App\Integrations\Tmdb\TmdbMedia;
use App\Integrations\Tmdb\TmdbMediaDetails;
use App\Integrations\Tmdb\TmdbSeasonDetails;
use PHPUnit\Framework\TestCase;

final class HomeEditorialServiceTest extends TestCase
{
    public function testBuildsDeterministicHeroAndShelvesWithOnlyTwoCollectionsAndOneConfiguration(): void
    {
        $catalog = new HomeCatalogFake();
        $catalog->trending = [
            $catalog->media(1, backdrop: null),
            $catalog->media(2, type: 'series', backdrop: '/hero.jpg'),
            $catalog->media(3, adult: true),
            $catalog->media(2, type: 'series', backdrop: '/duplicate.jpg'),
        ];
        $catalog->launches = [$catalog->media(4), $catalog->media(5)];

        $page = (new HomeEditorialService($catalog, new HomeImageService($catalog)))->page();

        self::assertSame(2, $page->hero?->sourceId);
        self::assertSame([1], array_map(static fn (TmdbMedia $media): int => $media->sourceId, $page->trending));
        self::assertSame([4, 5], array_map(static fn (TmdbMedia $media): int => $media->sourceId, $page->launches));
        self::assertSame('https://image.tmdb.test/t/p/w780/hero.jpg', $page->backdropUrl($page->hero));
        self::assertSame(['trendingAll', 'nowPlayingMovies', 'configuration'], $catalog->calls);
        self::assertFalse($page->trendingUnavailable);
        self::assertFalse($page->launchesUnavailable);
    }

    public function testIsolatesOfflineCollectionsAndReturnsSafeFallbackPage(): void
    {
        $catalog = new HomeCatalogFake();
        $catalog->fail = true;

        $page = (new HomeEditorialService($catalog, new HomeImageService($catalog)))->page();

        self::assertNull($page->hero);
        self::assertSame([], $page->trending);
        self::assertSame([], $page->launches);
        self::assertTrue($page->trendingUnavailable);
        self::assertTrue($page->launchesUnavailable);
        self::assertSame(['trendingAll', 'nowPlayingMovies'], $catalog->calls);
    }
}

final class HomeCatalogFake implements TmdbCatalog, TmdbDiscoveryCatalog
{
    /** @var list<string> */
    public array $calls = [];
    /** @var list<TmdbMedia> */
    public array $trending = [];
    /** @var list<TmdbMedia> */
    public array $launches = [];
    public bool $fail = false;

    public function configured(): bool { return true; }
    public function configuration(): TmdbImageConfiguration { $this->calls[] = 'configuration'; return new TmdbImageConfiguration('https://image.tmdb.test/t/p/', ['w342'], ['w780']); }
    public function trendingAll(): array { return $this->collection('trendingAll', $this->trending); }
    public function nowPlayingMovies(): array { return $this->collection('nowPlayingMovies', $this->launches); }
    public function trendingMovies(): array { return $this->emptyPage(); }
    public function trendingSeries(): array { return $this->emptyPage(); }
    public function upcomingMovies(): array { return $this->emptyPage(); }
    public function popularMovies(): array { return $this->emptyPage(); }
    public function popularSeries(): array { return $this->emptyPage(); }
    public function topRatedMovies(): array { return $this->emptyPage(); }
    public function topRatedSeries(): array { return $this->emptyPage(); }
    public function bestMoviesOfYear(int $year): array { return $this->emptyPage(); }
    public function bestSeriesOfYear(int $year): array { return $this->emptyPage(); }
    public function bestAnimeMoviesOfYear(int $year): array { return $this->emptyPage(); }
    public function bestAnimeSeriesOfYear(int $year): array { return $this->emptyPage(); }
    public function searchMovies(string $query, int $page = 1): array { return $this->emptyPage(); }
    public function searchSeries(string $query, int $page = 1): array { return $this->emptyPage(); }
    public function discoverAnimeMovies(int $page = 1): array { return $this->emptyPage(); }
    public function discoverAnimeSeries(int $page = 1): array { return $this->emptyPage(); }
    public function movieDetails(int $id): TmdbMediaDetails { throw new \LogicException(); }
    public function seriesDetails(int $id): TmdbMediaDetails { throw new \LogicException(); }
    public function seasonDetails(int $seriesId, int $seasonNumber): TmdbSeasonDetails { throw new \LogicException(); }

    public function media(int $id, string $type = 'movie', ?string $backdrop = '/backdrop.jpg', bool $adult = false): TmdbMedia
    {
        return new TmdbMedia('tmdb', $type, $id, 'Título ' . $id, null, 'Resumo', '2028-01-01', 2028, '/' . $id . '.jpg', $backdrop, 8.2, 500, 50.0, [18], 'en', $adult);
    }

    /** @param list<TmdbMedia> $items */
    private function collection(string $name, array $items): array
    {
        $this->calls[] = $name;
        if ($this->fail) { throw new TmdbException('transport'); }
        return ['page' => 1, 'total_pages' => 1, 'total_results' => count($items), 'results' => $items];
    }

    private function emptyPage(): array
    {
        return ['page' => 1, 'total_pages' => 1, 'total_results' => 0, 'results' => []];
    }
}
