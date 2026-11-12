<?php

declare(strict_types=1);

namespace App\Social;

final readonly class PublicFavorite
{
    public function __construct(
        public string $mediaType,
        public int $sourceId,
        public string $title,
        public ?string $originalTitle,
        public ?string $releaseDate,
        public ?string $posterPath,
        public ?bool $isAnime,
        public ?int $rating,
    ) {
    }

    public function year(): ?int
    {
        return $this->releaseDate === null ? null : (int) substr($this->releaseDate, 0, 4);
    }

    public function url(): string
    {
        return ($this->mediaType === 'movie' ? '/filmes/' : '/series/') . $this->sourceId;
    }
}
