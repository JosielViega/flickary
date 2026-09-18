<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb;

final class TmdbMediaId
{
    public static function parse(string $value): ?int
    {
        if (preg_match('/^[1-9]\d{0,9}$/D', $value) !== 1) {
            return null;
        }

        $id = (int) $value;
        return $id <= 2147483647 ? $id : null;
    }
}
