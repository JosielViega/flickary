<?php

declare(strict_types=1);

namespace App\Statistics;

interface StatisticsReader
{
    public function readForUser(int $userId, string $today): PersonalStatistics;
}
