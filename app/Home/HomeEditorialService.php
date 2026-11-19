<?php

declare(strict_types=1);

namespace App\Home;

use App\Integrations\Tmdb\TmdbDiscoveryCatalog;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbMedia;

final class HomeEditorialService
{
    private const SHELF_LIMIT = 10;

    public function __construct(
        private readonly TmdbDiscoveryCatalog $discovery,
        private readonly HomeImageService $images,
    ) {
    }

    public function page(): HomeEditorialPage
    {
        [$trendingPool, $trendingUnavailable] = $this->collection(fn (): array => $this->discovery->trendingAll()['results']);
        [$launchesPool, $launchesUnavailable] = $this->collection(fn (): array => $this->discovery->nowPlayingMovies()['results']);
        $hero = $this->hero($trendingPool);
        $heroKey = $hero === null ? null : $this->key($hero);
        $trending = array_values(array_filter(
            $trendingPool,
            fn (TmdbMedia $media): bool => $this->key($media) !== $heroKey,
        ));
        $trending = array_slice($trending, 0, self::SHELF_LIMIT);
        $launches = array_slice($launchesPool, 0, self::SHELF_LIMIT);

        $posterUrls = [];
        $backdropUrls = [];
        foreach (array_merge($hero === null ? [] : [$hero], $trending, $launches) as $media) {
            $key = $this->key($media);
            $poster = $this->images->poster($media->posterPath);
            $backdrop = $this->images->backdrop($media->backdropPath);
            if ($poster !== null) { $posterUrls[$key] = $poster; }
            if ($backdrop !== null) { $backdropUrls[$key] = $backdrop; }
        }

        return new HomeEditorialPage(
            $hero,
            $trending,
            $launches,
            $posterUrls,
            $backdropUrls,
            $trendingUnavailable,
            $launchesUnavailable,
        );
    }

    /** @return array{list<TmdbMedia>,bool} */
    private function collection(\Closure $loader): array
    {
        try {
            $items = $loader();
        } catch (TmdbException) {
            return [[], true];
        }
        $result = [];
        $seen = [];
        foreach ($items as $media) {
            if (!$media instanceof TmdbMedia || $media->adult === true || !in_array($media->mediaType, ['movie', 'series'], true)) {
                continue;
            }
            $key = $this->key($media);
            if (isset($seen[$key])) { continue; }
            $seen[$key] = true;
            $result[] = $media;
        }
        return [$result, false];
    }

    /** @param list<TmdbMedia> $items */
    private function hero(array $items): ?TmdbMedia
    {
        foreach ($items as $media) {
            if ($media->backdropPath !== null) { return $media; }
        }
        foreach ($items as $media) {
            if ($media->posterPath !== null) { return $media; }
        }
        return $items[0] ?? null;
    }

    private function key(TmdbMedia $media): string
    {
        return $media->mediaType . ':' . $media->sourceId;
    }
}
