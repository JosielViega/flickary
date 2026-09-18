<?php

declare(strict_types=1);

namespace App\Media;

interface UserSeriesProgressStore
{
    /** @return list<int> */
    public function watchedEpisodeNumbersForSeason(
        int $userId,
        string $source,
        int $seriesId,
        int $season,
    ): array;

    /** @return array<int, int> */
    public function countsBySeason(int $userId, string $source, int $seriesId): array;

    public function countForSeries(int $userId, string $source, int $seriesId): int;

    public function markWatched(
        int $userId,
        string $source,
        int $seriesId,
        int $season,
        int $episode,
    ): void;

    public function unmarkWatched(
        int $userId,
        string $source,
        int $seriesId,
        int $season,
        int $episode,
    ): void;

    /** @param list<int> $episodes */
    public function markSeasonWatched(
        int $userId,
        string $source,
        int $seriesId,
        int $season,
        array $episodes,
    ): void;

    public function clearSeason(int $userId, string $source, int $seriesId, int $season): void;
}
