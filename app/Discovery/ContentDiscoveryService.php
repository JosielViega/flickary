<?php

declare(strict_types=1);

namespace App\Discovery;

use App\Integrations\Tmdb\TmdbAnimeClassifier;
use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbDiscoveryCatalog;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageSizeSelector;
use App\Integrations\Tmdb\TmdbImageUrlBuilder;
use App\Integrations\Tmdb\TmdbMedia;
use Closure;

final class ContentDiscoveryService
{
    public const FILTERS = ['todos', 'filmes', 'series', 'anime'];
    private const SHELF_LIMIT = 12;
    private readonly Closure $now;

    public function __construct(
        private readonly TmdbDiscoveryCatalog $discovery,
        private readonly TmdbCatalog $catalog,
        private readonly TmdbAnimeClassifier $anime = new TmdbAnimeClassifier(),
        ?Closure $now = null,
    ) {
        $this->now = $now ?? static fn (): \DateTimeImmutable => new \DateTimeImmutable('now');
    }

    public function page(string $filter): DiscoveryPage
    {
        $filter = in_array($filter, self::FILTERS, true) ? $filter : 'todos';
        $year = (int) ($this->now)()->format('Y');
        $shelves = match ($filter) {
            'filmes' => $this->movieShelves($year),
            'series' => $this->seriesShelves($year),
            'anime' => $this->animeShelves($year),
            default => $this->allShelves($year),
        };
        return new DiscoveryPage($filter, $year, $shelves, $this->posterUrls($shelves));
    }

    /** @return list<DiscoveryShelf> */
    private function allShelves(int $year): array
    {
        return [
            $this->shelf('trending','Em alta agora','Os títulos que estão movimentando o TMDB hoje.',fn (): array => $this->discovery->trendingAll()['results']),
            $this->shelf('now-playing','Lançamentos no cinema','Filmes em cartaz e lançamentos recentes no Brasil.',fn (): array => $this->discovery->nowPlayingMovies()['results']),
            $this->shelf('popular','Populares','Filmes e séries populares, combinados sem misturar seus rankings.',fn (): array => $this->interleave($this->discovery->popularMovies()['results'],$this->discovery->popularSeries()['results'])),
            $this->shelf('best-year','Melhores de ' . $year,'Títulos bem avaliados no TMDB com volume mínimo de votos.',fn (): array => $this->interleave($this->discovery->bestMoviesOfYear($year)['results'],$this->discovery->bestSeriesOfYear($year)['results'])),
            $this->shelf('anime-highlight','Animes em destaque','Séries e filmes de animação em idioma original japonês.',fn (): array => $this->interleave($this->catalog->discoverAnimeSeries()['results'],$this->catalog->discoverAnimeMovies()['results']),true,'/anime','Explorar todo o catálogo Anime'),
            $this->shelf('upcoming','Em breve','Próximos lançamentos de cinema previstos para o Brasil.',fn (): array => $this->discovery->upcomingMovies()['results']),
        ];
    }

    /** @return list<DiscoveryShelf> */
    private function movieShelves(int $year): array
    {
        return [
            $this->shelf('movie-trending','Em alta nos filmes',null,fn (): array => $this->discovery->trendingMovies()['results']),
            $this->shelf('movie-now-playing','Em cartaz','Filmes em exibição e lançamentos recentes no Brasil.',fn (): array => $this->discovery->nowPlayingMovies()['results']),
            $this->shelf('movie-popular','Filmes populares',null,fn (): array => $this->discovery->popularMovies()['results']),
            $this->shelf('movie-best-year','Melhores filmes de ' . $year,'Bem avaliados no TMDB com pelo menos 100 votos.',fn (): array => $this->discovery->bestMoviesOfYear($year)['results']),
            $this->shelf('movie-top-rated','Mais bem avaliados',null,fn (): array => $this->discovery->topRatedMovies()['results']),
            $this->shelf('movie-upcoming','Em breve','Próximos lançamentos previstos para o Brasil.',fn (): array => $this->discovery->upcomingMovies()['results']),
        ];
    }

    /** @return list<DiscoveryShelf> */
    private function seriesShelves(int $year): array
    {
        return [
            $this->shelf('series-trending','Séries em alta',null,fn (): array => $this->discovery->trendingSeries()['results']),
            $this->shelf('series-popular','Séries populares',null,fn (): array => $this->discovery->popularSeries()['results']),
            $this->shelf('series-best-year','Melhores séries de ' . $year,'Bem avaliadas no TMDB com pelo menos 50 votos.',fn (): array => $this->discovery->bestSeriesOfYear($year)['results']),
            $this->shelf('series-top-rated','Mais bem avaliadas',null,fn (): array => $this->discovery->topRatedSeries()['results']),
        ];
    }

    /** @return list<DiscoveryShelf> */
    private function animeShelves(int $year): array
    {
        return [
            $this->shelf('anime-highlight','Animes em destaque','Filmes e séries Anime populares no TMDB.',fn (): array => $this->interleave($this->catalog->discoverAnimeSeries()['results'],$this->catalog->discoverAnimeMovies()['results']),true,'/anime','Explorar todo o catálogo Anime'),
            $this->shelf('anime-series','Séries Anime',null,fn (): array => $this->catalog->discoverAnimeSeries()['results'],true),
            $this->shelf('anime-movies','Filmes Anime',null,fn (): array => $this->catalog->discoverAnimeMovies()['results'],true),
            $this->shelf('anime-best-year','Melhores Animes de ' . $year,'Títulos do ano com pelo menos 25 votos no TMDB.',fn (): array => $this->interleave($this->discovery->bestAnimeSeriesOfYear($year)['results'],$this->discovery->bestAnimeMoviesOfYear($year)['results']),true),
        ];
    }

    /** @param Closure():list<TmdbMedia> $loader */
    private function shelf(string $key, string $title, ?string $subtitle, Closure $loader, bool $animeOnly = false, ?string $ctaUrl = null, ?string $ctaLabel = null): DiscoveryShelf
    {
        try {
            $items = $this->curate($loader(), $animeOnly);
            return new DiscoveryShelf($key,$title,$subtitle,$items,null,$ctaUrl,$ctaLabel);
        } catch (TmdbException $exception) {
            return new DiscoveryShelf($key,$title,$subtitle,[],$exception->category,$ctaUrl,$ctaLabel);
        }
    }

    /** @param list<TmdbMedia> $items @return list<TmdbMedia> */
    private function curate(array $items, bool $animeOnly): array
    {
        $result = [];
        $seen = [];
        foreach ($items as $media) {
            if (!$media instanceof TmdbMedia || $media->adult === true || ($animeOnly && !$this->anime->media($media))) {
                continue;
            }
            $key = $media->mediaType . ':' . $media->sourceId;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $media;
            if (count($result) === self::SHELF_LIMIT) {
                break;
            }
        }
        return $result;
    }

    /** @param list<TmdbMedia> $first @param list<TmdbMedia> $second @return list<TmdbMedia> */
    private function interleave(array $first, array $second): array
    {
        $result = [];
        $maximum = max(count($first), count($second));
        for ($index = 0; $index < $maximum; $index++) {
            if (isset($first[$index])) { $result[] = $first[$index]; }
            if (isset($second[$index])) { $result[] = $second[$index]; }
        }
        return $result;
    }

    /** @param list<DiscoveryShelf> $shelves @return array<string,string> */
    private function posterUrls(array $shelves): array
    {
        $hasPoster = false;
        foreach ($shelves as $shelf) {
            foreach ($shelf->items as $media) {
                if ($media->posterPath !== null) { $hasPoster = true; break 2; }
            }
        }
        if (!$hasPoster) { return []; }
        try {
            $configuration = $this->catalog->configuration();
        } catch (TmdbException) {
            return [];
        }
        $size = (new TmdbImageSizeSelector())->compactPoster($configuration->posterSizes);
        if ($size === null) { return []; }
        $builder = new TmdbImageUrlBuilder($configuration);
        $urls = [];
        foreach ($shelves as $shelf) {
            foreach ($shelf->items as $media) {
                $url = $builder->posterUrl($media->posterPath, $size);
                if ($url !== null) { $urls[$media->mediaType . ':' . $media->sourceId] = $url; }
            }
        }
        return $urls;
    }
}
