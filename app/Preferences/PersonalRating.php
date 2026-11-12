<?php

declare(strict_types=1);

namespace App\Preferences;

final class PersonalRating
{
    public static function parse(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) && $value >= 1 && $value <= 10) {
            return $value;
        }

        if (is_string($value) && in_array($value, array_map('strval', range(1, 10)), true)) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('Invalid personal rating.');
    }
}
