<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb;

final readonly class TmdbSeasonSummary
{
    public function __construct(
        public ?int $id,
        public int $seasonNumber,
        public string $name,
        public int $episodeCount,
        public ?string $airDate,
        public ?string $posterPath,
    ) {
    }
}
