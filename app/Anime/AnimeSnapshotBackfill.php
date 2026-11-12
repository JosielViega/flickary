<?php

declare(strict_types=1);

namespace App\Anime;

use App\Integrations\Tmdb\TmdbAnimeClassifier;
use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbException;

final class AnimeSnapshotBackfill
{
    public function __construct(
        private readonly AnimeSnapshotBackfillStore $store,
        private readonly TmdbCatalog $tmdb,
        private readonly TmdbAnimeClassifier $classifier = new TmdbAnimeClassifier(),
    ) {
    }

    public function run(): AnimeSnapshotBackfillResult
    {
        $initial = $this->store->countPendingTmdb();
        if ($initial === 0) {
            return new AnimeSnapshotBackfillResult(0, 0, 0, 0, 0, 0);
        }
        if (!$this->tmdb->configured()) {
            return new AnimeSnapshotBackfillResult($initial, 0, 0, 0, 0, $initial, 'not_configured');
        }

        $movies = 0;
        $series = 0;
        $anime = 0;
        $nonAnime = 0;
        $stopped = null;

        foreach ($this->store->pendingMovieIds() as $id) {
            try {
                $movies++;
                $classification = $this->classifier->details($this->tmdb->movieDetails($id));
                $updated = $this->store->fillMovie($id, $classification);
                $classification ? $anime += $updated : $nonAnime += $updated;
            } catch (TmdbException $exception) {
                if (in_array($exception->category, ['not_found', 'unexpected_payload'], true)) {
                    continue;
                }
                $stopped = $exception->category;
                break;
            }
        }

        if ($stopped === null) {
            foreach ($this->store->pendingSeriesIds() as $id) {
                try {
                    $series++;
                    $classification = $this->classifier->details($this->tmdb->seriesDetails($id));
                    $updated = $this->store->fillSeries($id, $classification);
                    $classification ? $anime += $updated : $nonAnime += $updated;
                } catch (TmdbException $exception) {
                    if (in_array($exception->category, ['not_found', 'unexpected_payload'], true)) {
                        continue;
                    }
                    $stopped = $exception->category;
                    break;
                }
            }
        }

        return new AnimeSnapshotBackfillResult(
            $initial,
            $movies,
            $series,
            $anime,
            $nonAnime,
            $this->store->countPendingTmdb(),
            $stopped,
        );
    }
}
