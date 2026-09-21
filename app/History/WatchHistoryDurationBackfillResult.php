<?php

declare(strict_types=1);

namespace App\History;

final readonly class WatchHistoryDurationBackfillResult
{
    public function __construct(
        public int $initiallyMissing,
        public int $filled,
        public int $remaining,
        public int $moviesRequested,
        public int $seasonsRequested,
        public ?string $stoppedReason = null,
    ) {
    }
}
