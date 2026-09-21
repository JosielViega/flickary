<?php

declare(strict_types=1);

namespace App\History;

use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbException;

final class WatchHistoryDurationBackfill
{
    public function __construct(
        private readonly WatchHistoryDurationStore $history,
        private readonly TmdbCatalog $tmdb,
    ) {
    }

    public function run(): WatchHistoryDurationBackfillResult
    {
        if (!$this->tmdb->configured()) {
            throw new \RuntimeException('TMDB integration is not configured. No records were changed.');
        }

        $initiallyMissing = $this->history->countMissingDuration();
        $filled = 0;
        $moviesRequested = 0;
        $seasonsRequested = 0;
        $stoppedReason = null;

        foreach ($this->history->missingMovieIdentities() as $identity) {
            if ($identity['source'] !== 'tmdb') {
                continue;
            }
            $moviesRequested++;
            try {
                $details = $this->tmdb->movieDetails($identity['source_id']);
            } catch (TmdbException $exception) {
                if ($this->canSkip($exception)) {
                    continue;
                }
                $stoppedReason = $exception->category;
                break;
            }
            $duration = WatchDuration::normalize($details->runtime);
            if ($duration !== null) {
                $filled += $this->history->fillMovieDuration('tmdb', $identity['source_id'], $duration);
            }
        }

        if ($stoppedReason === null) {
            foreach ($this->history->missingEpisodeSeasons() as $identity) {
                if ($identity['source'] !== 'tmdb') {
                    continue;
                }
                $seasonsRequested++;
                try {
                    $season = $this->tmdb->seasonDetails($identity['source_id'], $identity['season_number']);
                } catch (TmdbException $exception) {
                    if ($this->canSkip($exception)) {
                        continue;
                    }
                    $stoppedReason = $exception->category;
                    break;
                }
                foreach ($season->episodes as $episode) {
                    $duration = WatchDuration::normalize($episode->runtime);
                    if ($duration !== null) {
                        $filled += $this->history->fillEpisodeDuration(
                            'tmdb',
                            $identity['source_id'],
                            $identity['season_number'],
                            $episode->episodeNumber,
                            $duration,
                        );
                    }
                }
            }
        }

        return new WatchHistoryDurationBackfillResult(
            $initiallyMissing,
            $filled,
            $this->history->countMissingDuration(),
            $moviesRequested,
            $seasonsRequested,
            $stoppedReason,
        );
    }

    private function canSkip(TmdbException $exception): bool
    {
        return in_array($exception->category, ['not_found', 'unexpected_payload'], true);
    }
}
