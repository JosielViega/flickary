<?php

declare(strict_types=1);

namespace App\Statistics;

final readonly class MonthlyStatistic
{
    public function __construct(
        public string $month,
        public string $label,
        public int $count,
    ) {
    }
}
