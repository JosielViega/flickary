<?php

declare(strict_types=1);

namespace App\Social;

final readonly class PublicProfileStatistics
{
    public function __construct(
        public int $libraryItems,
        public int $favoriteItems,
        public int $ratedItems,
        public ?float $averageRating,
        public int $historyViews,
        public int $knownMinutes,
        public int $unknownDurationViews,
    ) {
    }
}
