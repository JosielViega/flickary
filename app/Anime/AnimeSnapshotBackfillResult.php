<?php

declare(strict_types=1);

namespace App\Anime;

final readonly class AnimeSnapshotBackfillResult
{
    public function __construct(
        public int $initiallyPending,
        public int $moviesRequested,
        public int $seriesRequested,
        public int $classifiedAnime,
        public int $classifiedNonAnime,
        public int $remaining,
        public ?string $stoppedReason = null,
    ) {
    }
}
