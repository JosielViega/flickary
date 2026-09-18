<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb;

final readonly class TmdbMediaDetails
{
    /**
     * @param list<array{id:int,name:string}> $genres
     * @param list<TmdbSeasonSummary> $seasons
     */
    public function __construct(
        public string $source,
        public string $mediaType,
        public int $sourceId,
        public string $title,
        public ?string $originalTitle,
        public ?string $tagline,
        public ?string $overview,
        public ?string $releaseDate,
        public ?int $year,
        public ?string $posterPath,
        public ?string $backdropPath,
        public array $genres,
        public ?float $voteAverage,
        public ?int $voteCount,
        public ?float $popularity,
        public ?string $originalLanguage,
        public ?bool $adult,
        public ?string $status,
        public ?int $runtime,
        public ?int $numberOfSeasons,
        public ?int $numberOfEpisodes,
        public ?string $lastAirDate,
        public ?bool $inProduction,
        public array $seasons = [],
    ) {
    }
}
