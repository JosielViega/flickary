<?php

declare(strict_types=1);

namespace App\Preferences;

final readonly class UserMediaPreference
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $source,
        public string $mediaType,
        public int $sourceId,
        public string $title,
        public ?string $originalTitle,
        public ?string $releaseDate,
        public ?string $posterPath,
        public ?bool $isAnime,
        public bool $isFavorite,
        public ?int $rating,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public function year(): ?int
    {
        return $this->releaseDate === null ? null : (int) substr($this->releaseDate, 0, 4);
    }
}
