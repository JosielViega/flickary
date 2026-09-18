<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb;

final readonly class TmdbSeasonDetails
{
    /** @param list<TmdbEpisode> $episodes */
    public function __construct(
        public string $source,
        public int $seriesSourceId,
        public ?int $seasonId,
        public int $seasonNumber,
        public string $name,
        public ?string $overview,
        public ?string $airDate,
        public ?string $posterPath,
        public array $episodes,
    ) {
    }
}
