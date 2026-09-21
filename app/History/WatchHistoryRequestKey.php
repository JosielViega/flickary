<?php

declare(strict_types=1);

namespace App\History;

final class WatchHistoryRequestKey
{
    public static function generate(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function parse(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[a-f0-9]{32}$/D', $value) === 1
            ? $value
            : null;
    }
}
