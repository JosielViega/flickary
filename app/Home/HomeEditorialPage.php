<?php

declare(strict_types=1);

namespace App\Home;

use App\Integrations\Tmdb\TmdbMedia;

final readonly class HomeEditorialPage
{
    /**
     * @param list<TmdbMedia> $trending
     * @param list<TmdbMedia> $launches
     * @param array<string,string> $posterUrls
     * @param array<string,string> $backdropUrls
     */
    public function __construct(
        public ?TmdbMedia $hero,
        public array $trending,
        public array $launches,
        public array $posterUrls,
        public array $backdropUrls,
        public bool $trendingUnavailable,
        public bool $launchesUnavailable,
    ) {
    }

    public function key(TmdbMedia $media): string
    {
        return $media->mediaType . ':' . $media->sourceId;
    }

    public function posterUrl(TmdbMedia $media): ?string
    {
        return $this->posterUrls[$this->key($media)] ?? null;
    }

    public function backdropUrl(TmdbMedia $media): ?string
    {
        return $this->backdropUrls[$this->key($media)] ?? null;
    }

    /** @return list<TmdbMedia> */
    public function journeyItems(): array
    {
        $items = array_filter([$this->hero, $this->trending[0] ?? null, $this->launches[0] ?? null]);
        return array_values($items);
    }
}
