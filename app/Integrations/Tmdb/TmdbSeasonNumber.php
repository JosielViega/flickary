<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb;

final class TmdbSeasonNumber
{
    public static function parse(string $value): ?int
    {
        if (preg_match('/^(?:0|[1-9]\d{0,9})$/D', $value) !== 1) {
            return null;
        }

        $number = (int) $value;

        return $number <= 2147483647 ? $number : null;
    }
}
