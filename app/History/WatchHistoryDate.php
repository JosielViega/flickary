<?php

declare(strict_types=1);

namespace App\History;

final class WatchHistoryDate
{
    public static function parse(mixed $value): ?string
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        if ($year < 1000 || !checkdate($month, $day, $year)) {
            return null;
        }

        return $value <= date('Y-m-d') ? $value : null;
    }
}
