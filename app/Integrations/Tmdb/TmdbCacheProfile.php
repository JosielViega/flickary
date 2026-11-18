<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb;

enum TmdbCacheProfile: string
{
    case Configuration = 'configuration';
    case Trending = 'trending';
    case Schedule = 'schedule';
    case Ranking = 'ranking';
    case Discover = 'discover';

    public function freshSeconds(): int
    {
        return match ($this) {
            self::Configuration => 86400,
            self::Trending => 600,
            self::Schedule, self::Ranking => 1800,
            self::Discover => 3600,
        };
    }

    public function staleSeconds(): int
    {
        return match ($this) {
            self::Configuration => 604800,
            self::Trending => 7200,
            self::Schedule, self::Ranking => 21600,
            self::Discover => 43200,
        };
    }
}
