<?php

declare(strict_types=1);

namespace App\Preferences;

use App\Integrations\Tmdb\TmdbMediaDetails;

final readonly class UserMediaPreferenceDraft
{
    public function __construct(
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
    ) {
        if ($source !== 'tmdb' || !in_array($mediaType, ['movie', 'series'], true)
            || $sourceId < 1 || trim($title) === ''
        ) {
            throw new \InvalidArgumentException('Invalid media preference snapshot.');
        }

        PersonalRating::parse($rating);
    }

    public static function fromDetails(
        TmdbMediaDetails $details,
        ?bool $isAnime,
        bool $isFavorite,
        ?int $rating,
    ): self {
        return new self(
            $details->source,
            $details->mediaType,
            $details->sourceId,
            $details->title,
            $details->originalTitle,
            $details->releaseDate,
            $details->posterPath,
            $isAnime,
            $isFavorite,
            $rating,
        );
    }
}
