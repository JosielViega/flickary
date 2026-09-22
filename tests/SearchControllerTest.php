<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\SearchController;
use App\Core\Request;
use App\Core\View;
use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageConfiguration;
use App\Integrations\Tmdb\TmdbMedia;
use PHPUnit\Framework\TestCase;

final class SearchControllerTest extends TestCase
{
    public function testInitialStateDoesNotCallTmdb(): void
    {
        $catalog = $this->catalog();
        $response = $this->search($catalog);

        self::assertSame(200, $response->status());
        self::assertSame([], $catalog->calls);
        self::assertStringContainsString('Seu próximo título começa aqui', $response->body());
        self::assertStringContainsString('autofocus', $response->body());
    }

    public function testRejectsShortLongAndControlQueriesWithoutCallingTmdb(): void
    {
        foreach (['a', str_repeat('a', 121), "Matrix\x00Test"] as $query) {
            $catalog = $this->catalog();
            $response = $this->search($catalog, ['q' => $query]);
            self::assertSame(422, $response->status());
            self::assertSame([], $catalog->calls);
            self::assertStringContainsString('Não foi possível pesquisar', $response->body());
        }
    }

    public function testEscapesReflectedQueryAndExternalFields(): void
    {
        $payload = '<script>alert(1)</script>';
        $catalog = $this->catalog(movies: [$this->media('movie', title: $payload, overview: $payload)]);
        $response = $this->search($catalog, ['q' => $payload, 'tipo' => 'filmes']);

        self::assertSame(200, $response->status());
        self::assertStringNotContainsString($payload, $response->body());
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $response->body());
    }

    public function testInvalidTypeFallsBackToAllAndAllUsesPageOne(): void
    {
        $catalog = $this->catalog(
            movies: array_fill(0, 10, $this->media('movie')),
            series: array_fill(0, 10, $this->media('series')),
        );
        $response = $this->search($catalog, ['q' => 'matrix', 'tipo' => 'invalid', 'page' => '44']);

        self::assertSame([['movie', 'matrix', 1], ['series', 'matrix', 1], ['configuration']], $catalog->calls);
        self::assertSame(16, substr_count($response->body(), '<article class="media-card">'));
        self::assertStringContainsString('tipo=filmes', $response->body());
        self::assertStringContainsString('tipo=series', $response->body());
    }

    public function testMovieSearchUsesOnlyMoviesAndRespectsSafePage(): void
    {
        $catalog = $this->catalog(movies: [$this->media('movie')], moviePages: 4);
        $response = $this->search($catalog, ['q' => 'matrix', 'tipo' => 'filmes', 'page' => '3']);

        self::assertSame([['movie', 'matrix', 3], ['configuration']], $catalog->calls);
        self::assertStringContainsString('Página 3 de 4', $response->body());
        self::assertStringContainsString('tipo=filmes&amp;page=2', $response->body());
        self::assertStringContainsString('tipo=filmes&amp;page=4', $response->body());
        self::assertStringNotContainsString('href="#"', $response->body());
    }

    public function testSeriesSearchUsesOnlySeriesAndInvalidPageFallsBackToOne(): void
    {
        $catalog = $this->catalog(series: [$this->media('series')], seriesPages: 2);
        $response = $this->search($catalog, ['q' => 'breaking bad', 'tipo' => 'series', 'page' => '9999']);

        self::assertSame([['series', 'breaking bad', 1], ['configuration']], $catalog->calls);
        self::assertStringContainsString('Série', $response->body());
        self::assertStringContainsString('Página 1 de 2', $response->body());
        self::assertStringNotContainsString('page=0', $response->body());
    }

    public function testRendersRealEmptyState(): void
    {
        $response = $this->search($this->catalog(), ['q' => 'sem resultado', 'tipo' => 'filmes']);
        self::assertStringContainsString('Nenhum resultado encontrado', $response->body());
        self::assertStringNotContainsString('<article class="media-card">', $response->body());
    }

    public function testMapsExpectedTmdbErrorsToFriendlyMessages(): void
    {
        foreach ([
            'not_configured' => 'não está disponível neste ambiente',
            'rate_limited' => 'Muitas buscas foram realizadas',
            'transport' => 'Não foi possível consultar o catálogo',
        ] as $category => $message) {
            $catalog = $this->catalog();
            $catalog->movieException = new TmdbException($category);
            $response = $this->search($catalog, ['q' => 'matrix', 'tipo' => 'filmes']);
            self::assertStringContainsString($message, $response->body());
            self::assertStringNotContainsString('Authorization', $response->body());
        }
    }

    public function testMissingPosterDoesNotCallConfigurationAndUsesFallback(): void
    {
        $catalog = $this->catalog(movies: [$this->media('movie', posterPath: null)]);
        $response = $this->search($catalog, ['q' => 'matrix', 'tipo' => 'filmes']);

        self::assertSame([['movie', 'matrix', 1]], $catalog->calls);
        self::assertStringContainsString('media-card__fallback', $response->body());
        self::assertStringNotContainsString('<img src="https://image.tmdb.org', $response->body());
    }

    public function testImageConfigurationFailureKeepsResultsWithoutBrokenImage(): void
    {
        $catalog = $this->catalog(movies: [$this->media('movie')]);
        $catalog->configurationException = new TmdbException('transport');
        $response = $this->search($catalog, ['q' => 'matrix', 'tipo' => 'filmes']);

        self::assertStringContainsString('Matrix', $response->body());
        self::assertStringContainsString('media-card__fallback', $response->body());
        self::assertStringNotContainsString('<img ', $response->body());
    }

    public function testPosterComesFromBuilderAndCardIsSemantic(): void
    {
        $response = $this->search(
            $this->catalog(movies: [$this->media('movie')]),
            ['q' => 'matrix', 'tipo' => 'filmes'],
        );

        self::assertStringContainsString('https://image.tmdb.org/t/p/w500/poster.jpg', $response->body());
        self::assertStringContainsString('alt="Pôster de Matrix"', $response->body());
        self::assertStringContainsString('loading="lazy"', $response->body());
        self::assertStringContainsString('decoding="async"', $response->body());
        self::assertStringContainsString('TMDB 8,3', $response->body());
        self::assertStringNotContainsString('IMDb', $response->body());
        self::assertStringContainsString('href="/filmes/603"', $response->body());
        self::assertMatchesRegularExpression('#<article class="media-card">\s*<a[^>]+href="/filmes/603"[^>]*>.*?</a></article>#s', $response->body());
        preg_match('#<article class="media-card">(.*?)</article>#s', $response->body(), $card);
        self::assertStringNotContainsString('<button', $card[1]);
    }

    public function testSeriesCardUsesItsRealDetailsRoute(): void
    {
        $response = $this->search(
            $this->catalog(series: [$this->media('series', title: 'Breaking Bad')]),
            ['q' => 'breaking bad', 'tipo' => 'series'],
        );

        self::assertStringContainsString('href="/series/1396"', $response->body());
        self::assertStringContainsString('aria-label="Ver detalhes de Breaking Bad"', $response->body());
        self::assertStringNotContainsString('href="#"', $response->body());
    }

    public function testSearchAddsAnimeBadgeFromExistingMetadataWithoutExtraRequests(): void
    {
        $catalog = $this->catalog(series: [$this->media('series', title: 'Naruto', posterPath: null, genreIds: [16, 10759], originalLanguage: 'ja')]);
        $response = $this->search($catalog, ['q'=>'naruto','tipo'=>'series']);

        self::assertSame([['series','naruto',1]], $catalog->calls);
        self::assertStringContainsString('Anime · Série', $response->body());
        self::assertStringContainsString('href="/anime"', $response->body());
    }

    public function testSearchDoesNotMislabelJapaneseLiveActionOrWesternAnimation(): void
    {
        $catalog = $this->catalog(movies: [
            $this->media('movie', title:'Godzilla', posterPath:null, genreIds:[28,18], originalLanguage:'ja'),
            $this->media('movie', title:'Toy Story', posterPath:null, genreIds:[16,10751], originalLanguage:'en'),
        ]);
        $body = $this->search($catalog, ['q'=>'controle','tipo'=>'filmes'])->body();

        self::assertSame(0, substr_count($body, 'Anime · Filme'));
        self::assertStringContainsString('Godzilla', $body);
        self::assertStringContainsString('Toy Story', $body);
    }

    public function testRequestedPageBeyondProviderTotalIsControlled(): void
    {
        $catalog = $this->catalog(movies: [$this->media('movie')], moviePages: 2);
        $response = $this->search($catalog, ['q' => 'matrix', 'tipo' => 'filmes', 'page' => '5']);
        self::assertStringContainsString('A página solicitada não está disponível', $response->body());
        self::assertStringNotContainsString('page=6', $response->body());

        $singlePage = $this->search(
            $this->catalog(movies: [$this->media('movie')]),
            ['q' => 'matrix', 'tipo' => 'filmes', 'page' => '2'],
        );
        self::assertStringContainsString('A página solicitada não está disponível', $singlePage->body());
    }

    private function search(TmdbCatalog $catalog, array $query = []): \App\Core\Response
    {
        $controller = new SearchController(
            new View(dirname(__DIR__) . '/resources/views'),
            $catalog,
        );
        return $controller->index(new Request(queryParams: $query));
    }

    private function media(
        string $type,
        string $title = 'Matrix',
        string $overview = 'Uma história.',
        ?string $posterPath = '/poster.jpg',
        array $genreIds = [18],
        ?string $originalLanguage = 'en',
    ): TmdbMedia {
        return new TmdbMedia(
            'tmdb',
            $type,
            $type === 'movie' ? 603 : 1396,
            $title,
            $title,
            $overview,
            $type === 'movie' ? '1999-05-21' : '2008-01-20',
            $type === 'movie' ? 1999 : 2008,
            $posterPath,
            '/backdrop.jpg',
            8.258,
            100,
            20.5,
            $genreIds,
            $originalLanguage,
            false,
        );
    }

    /** @return TmdbCatalog&object{calls:array,movieException:?TmdbException,configurationException:?TmdbException} */
    private function catalog(
        array $movies = [],
        array $series = [],
        int $moviePages = 1,
        int $seriesPages = 1,
    ): TmdbCatalog {
        return new class($movies, $series, $moviePages, $seriesPages) implements TmdbCatalog {
            public array $calls = [];
            public ?TmdbException $movieException = null;
            public ?TmdbException $seriesException = null;
            public ?TmdbException $configurationException = null;

            public function __construct(
                private array $movies,
                private array $series,
                private int $moviePages,
                private int $seriesPages,
            ) {
            }

            public function configured(): bool { return true; }

            public function movieDetails(int $id): \App\Integrations\Tmdb\TmdbMediaDetails
            {
                throw new \LogicException('Not used by search tests.');
            }

            public function seriesDetails(int $id): \App\Integrations\Tmdb\TmdbMediaDetails
            {
                throw new \LogicException('Not used by search tests.');
            }
            public function seasonDetails(int $seriesId,int $seasonNumber): \App\Integrations\Tmdb\TmdbSeasonDetails { throw new \LogicException('Not used.'); }

            public function configuration(): TmdbImageConfiguration
            {
                $this->calls[] = ['configuration'];
                if ($this->configurationException !== null) {
                    throw $this->configurationException;
                }
                return new TmdbImageConfiguration('https://image.tmdb.org/t/p/', ['w500'], ['w1280']);
            }

            public function searchMovies(string $query, int $page = 1): array
            {
                $this->calls[] = ['movie', $query, $page];
                if ($this->movieException !== null) {
                    throw $this->movieException;
                }
                return ['page' => $page, 'total_pages' => $this->moviePages, 'total_results' => count($this->movies), 'results' => $this->movies];
            }

            public function searchSeries(string $query, int $page = 1): array
            {
                $this->calls[] = ['series', $query, $page];
                if ($this->seriesException !== null) {
                    throw $this->seriesException;
                }
                return ['page' => $page, 'total_pages' => $this->seriesPages, 'total_results' => count($this->series), 'results' => $this->series];
            }

            public function discoverAnimeMovies(int $page = 1): array { throw new \LogicException('Not used.'); }
            public function discoverAnimeSeries(int $page = 1): array { throw new \LogicException('Not used.'); }
        };
    }
}
