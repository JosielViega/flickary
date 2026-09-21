<?php

declare(strict_types=1);

namespace App\Schedule;

final class ScheduleId
{
    public static function parse(string $value): ?int
    {
        if (preg_match('/^[1-9]\d{0,18}$/D', $value) !== 1) {
            return null;
        }
        $maximum = (string) PHP_INT_MAX;
        if (strlen($value) > strlen($maximum)
            || (strlen($value) === strlen($maximum) && strcmp($value, $maximum) > 0)
        ) {
            return null;
        }

        return (int) $value;
    }
}
