<?php

declare(strict_types=1);

namespace App\History;

interface WatchHistoryDurationStore
{
    public function countMissingDuration(): int;

    /** @return list<array{source:string, source_id:int}> */
    public function missingMovieIdentities(): array;

    /** @return list<array{source:string, source_id:int, season_number:int}> */
    public function missingEpisodeSeasons(): array;

    public function fillMovieDuration(string $source, int $sourceId, int $durationMinutes): int;

    public function fillEpisodeDuration(
        string $source,
        int $sourceId,
        int $seasonNumber,
        int $episodeNumber,
        int $durationMinutes,
    ): int;
}
