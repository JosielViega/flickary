<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb;

final class TmdbAnimeClassifier
{
    /** Validated against the official TMDB Movie and TV genre lists on 2026-09-22. */
    public const ANIMATION_GENRE_ID = 16;

    public function media(TmdbMedia $media): bool
    {
        return $media->originalLanguage === 'ja'
            && in_array(self::ANIMATION_GENRE_ID, $media->genreIds, true);
    }

    public function details(TmdbMediaDetails $details): bool
    {
        if ($details->originalLanguage !== 'ja') {
            return false;
        }

        foreach ($details->genres as $genre) {
            if (($genre['id'] ?? null) === self::ANIMATION_GENRE_ID) {
                return true;
            }
        }

        return false;
    }
}
