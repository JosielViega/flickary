<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb;

use Closure;

interface TmdbPayloadCache
{
    /** @param array<string, scalar> $query @param Closure():array<string,mixed> $loader @return array<string,mixed> */
    public function remember(string $path, array $query, TmdbCacheProfile $profile, Closure $loader): array;
}
