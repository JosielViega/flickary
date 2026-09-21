<?php

declare(strict_types=1);

namespace App\Statistics;

final readonly class PersonalStatistics
{
    /** @param list<MonthlyStatistic> $monthly @param array<string,int> $libraryByStatus */
    public function __construct(
        public int $totalViews,
        public int $movieViews,
        public int $episodeViews,
        public int $uniqueMovies,
        public int $uniqueSeries,
        public int $activeDays,
        public int $rewatches,
        public int $knownMinutes,
        public int $unknownDurationEvents,
        public int $currentMonthViews,
        public int $currentYearViews,
        public array $monthly,
        public array $libraryByStatus,
        public int $libraryTotal,
        public int $watchedEpisodeProgress,
        public int $scheduleTotal,
        public int $scheduleToday,
        public int $scheduleFuture,
        public int $scheduleOverdue,
    ) {
    }

    public function hasAnyData(): bool
    {
        return $this->totalViews > 0 || $this->libraryTotal > 0
            || $this->watchedEpisodeProgress > 0 || $this->scheduleTotal > 0;
    }
}
