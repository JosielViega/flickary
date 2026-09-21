<?php

declare(strict_types=1);

namespace App\History;

final class WatchHistoryId
{
    public static function parse(string $value): ?int
    {
        if (preg_match('/^[1-9]\d{0,18}$/D', $value) !== 1) {
            return null;
        }

        $normalized = ltrim($value, '0');
        $maximum = (string) PHP_INT_MAX;

        if (strlen($normalized) > strlen($maximum)
            || (strlen($normalized) === strlen($maximum) && strcmp($normalized, $maximum) > 0)
        ) {
            return null;
        }

        return (int) $normalized;
    }
}
