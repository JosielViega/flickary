<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb;

final readonly class TmdbMedia
{
    /** @param list<int> $genreIds */
    public function __construct(
        public string $source,
        public string $mediaType,
        public int $sourceId,
        public string $title,
        public ?string $originalTitle,
        public ?string $overview,
        public ?string $releaseDate,
        public ?int $year,
        public ?string $posterPath,
        public ?string $backdropPath,
        public ?float $voteAverage,
        public ?int $voteCount,
        public ?float $popularity,
        public array $genreIds,
        public ?string $originalLanguage,
        public ?bool $adult,
    ) {
    }
}
