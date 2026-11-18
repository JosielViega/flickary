<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb;

interface TmdbDiscoveryCatalog
{
    /** @return array{page:int,total_pages:int,total_results:int,results:list<TmdbMedia>} */
    public function trendingAll(): array;
    /** @return array{page:int,total_pages:int,total_results:int,results:list<TmdbMedia>} */
    public function trendingMovies(): array;
    /** @return array{page:int,total_pages:int,total_results:int,results:list<TmdbMedia>} */
    public function trendingSeries(): array;
    /** @return array{page:int,total_pages:int,total_results:int,results:list<TmdbMedia>} */
    public function nowPlayingMovies(): array;
    /** @return array{page:int,total_pages:int,total_results:int,results:list<TmdbMedia>} */
    public function upcomingMovies(): array;
    /** @return array{page:int,total_pages:int,total_results:int,results:list<TmdbMedia>} */
    public function popularMovies(): array;
    /** @return array{page:int,total_pages:int,total_results:int,results:list<TmdbMedia>} */
    public function popularSeries(): array;
    /** @return array{page:int,total_pages:int,total_results:int,results:list<TmdbMedia>} */
    public function topRatedMovies(): array;
    /** @return array{page:int,total_pages:int,total_results:int,results:list<TmdbMedia>} */
    public function topRatedSeries(): array;
    /** @return array{page:int,total_pages:int,total_results:int,results:list<TmdbMedia>} */
    public function bestMoviesOfYear(int $year): array;
    /** @return array{page:int,total_pages:int,total_results:int,results:list<TmdbMedia>} */
    public function bestSeriesOfYear(int $year): array;
    /** @return array{page:int,total_pages:int,total_results:int,results:list<TmdbMedia>} */
    public function bestAnimeMoviesOfYear(int $year): array;
    /** @return array{page:int,total_pages:int,total_results:int,results:list<TmdbMedia>} */
    public function bestAnimeSeriesOfYear(int $year): array;
}
