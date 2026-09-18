<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb;

final readonly class TmdbEpisode
{
    public function __construct(
        public ?int $id,
        public int $seasonNumber,
        public int $episodeNumber,
        public string $name,
        public ?string $overview,
        public ?string $airDate,
        public ?int $runtime,
        public ?string $stillPath,
    ) {
    }
}
