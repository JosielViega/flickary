<?php

declare(strict_types=1);

namespace App\Discovery;

use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbDiscoveryCatalog;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbMedia;
use App\Integrations\Tmdb\TmdbMediaDetails;

final class MediaCompanionShelfService
{
    private const LIMIT = 10;

    public function __construct(
        private readonly TmdbDiscoveryCatalog $discovery,
        private readonly TmdbCatalog $catalog,
    ) {
    }

    /** @return list<TmdbMedia> */
    public function forDetails(TmdbMediaDetails $details, bool $isAnime): array
    {
        try {
            $items = $isAnime
                ? ($details->mediaType === 'movie'
                    ? $this->catalog->discoverAnimeMovies()['results']
                    : $this->catalog->discoverAnimeSeries()['results'])
                : ($details->mediaType === 'movie'
                    ? $this->discovery->trendingMovies()['results']
                    : $this->discovery->trendingSeries()['results']);
        } catch (TmdbException) {
            return [];
        }

        $result = [];
        $seen = [];
        foreach ($items as $media) {
            if (!$media instanceof TmdbMedia || $media->adult === true) {
                continue;
            }
            $key = $media->mediaType . ':' . $media->sourceId;
            if ($key === $details->mediaType . ':' . $details->sourceId || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $media;
            if (count($result) === self::LIMIT) {
                break;
            }
        }

        return $result;
    }
}
