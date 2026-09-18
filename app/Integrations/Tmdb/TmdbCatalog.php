<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb;

interface TmdbCatalog
{
    public function configured(): bool;

    public function configuration(): TmdbImageConfiguration;

    public function movieDetails(int $id): TmdbMediaDetails;

    public function seriesDetails(int $id): TmdbMediaDetails;
    public function seasonDetails(int $seriesId, int $seasonNumber): TmdbSeasonDetails;

    /** @return array{page:int,total_pages:int,total_results:int,results:list<TmdbMedia>} */
    public function searchMovies(string $query, int $page = 1): array;

    /** @return array{page:int,total_pages:int,total_results:int,results:list<TmdbMedia>} */
    public function searchSeries(string $query, int $page = 1): array;
}
