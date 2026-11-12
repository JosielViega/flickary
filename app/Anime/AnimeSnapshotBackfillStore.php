<?php

declare(strict_types=1);

namespace App\Anime;

interface AnimeSnapshotBackfillStore
{
    public function countPendingTmdb(): int;

    /** @return list<int> */
    public function pendingMovieIds(): array;

    /** @return list<int> */
    public function pendingSeriesIds(): array;

    public function fillMovie(int $sourceId, bool $isAnime): int;

    public function fillSeries(int $sourceId, bool $isAnime): int;
}
