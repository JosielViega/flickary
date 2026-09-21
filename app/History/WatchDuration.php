<?php

declare(strict_types=1);

namespace App\History;

final class WatchDuration
{
    public static function normalize(mixed $value): ?int
    {
        return is_int($value) && $value >= 1 && $value <= 65535 ? $value : null;
    }

    public static function format(int $minutes): string
    {
        if ($minutes < 0) {
            throw new \InvalidArgumentException('Duration cannot be negative.');
        }
        if ($minutes < 60) {
            return $minutes . ' min';
        }

        return intdiv($minutes, 60) . ' h ' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) . ' min';
    }
}
