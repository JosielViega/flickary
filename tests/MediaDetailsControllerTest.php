<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\MediaDetailsController;
use App\Core\View;
use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageConfiguration;
use App\Integrations\Tmdb\TmdbMediaDetails;
use PHPUnit\Framework\TestCase;

final class MediaDetailsControllerTest extends TestCase
{
    public function testMovieRendersPublicDetailsAndBuildsBothImagesOnce(): void
    {
        $catalog = $this->catalog(movie: $this->details('movie'));
        $response = $this->controller($catalog)->movie('603');

        self::assertSame(200, $response->status());
        self::assertSame([['movie', 603], ['configuration']], $catalog->calls);
        self::assertStringContainsString('<title>Matrix — Flickary</title>', $response->body());
        self::assertStringContainsString('Título original: The Matrix', $response->body());
        self::assertStringContainsString('31/03/1999', $response->body());
        self::assertStringContainsString('Ação', $response->body());
        self::assertStringContainsString('136 min', $response->body());
        self::assertStringContainsString('TMDB 8,2', $response->body());
        self::assertStringContainsString('26.789 votos', $response->body());
        self::assertStringContainsString('https://image.tmdb.org/t/p/w500/poster.jpg', $response->body());
        self::assertStringContainsString('https://image.tmdb.org/t/p/w1280/backdrop.jpg', $response->body());
        self::assertStringContainsString('alt="Pôster de Matrix"', $response->body());
        self::assertStringContainsString('href="/sobre#tmdb"', $response->body());
        self::assertStringContainsString('href="/buscar"', $response->body());
        self::assertStringContainsString('href="/login"', $response->body());
        self::assertStringContainsString('Entre para adicionar à sua lista', $response->body());
        self::assertStringNotContainsString('IMDb', $response->body());
    }

    public function testSeriesRendersAggregatesWithoutSeasonNavigation(): void
    {
        $catalog = $this->catalog(series: $this->details('series'));
        $response = $this->controller($catalog)->series('1396');

        self::assertSame(200, $response->status());
        self::assertSame([['series', 1396], ['configuration']], $catalog->calls);
        self::assertStringContainsString('Breaking Bad — Flickary', $response->body());
        self::assertStringContainsString('<dt>Temporadas</dt><dd>5</dd>', $response->body());
        self::assertStringContainsString('<dt>Episódios</dt><dd>62</dd>', $response->body());
        self::assertStringNotContainsString('Temporada 1', $response->body());
        self::assertStringNotContainsString('/temporadas/', $response->body());
    }

    public function testAnimeMovieAndSeriesShowClassificationWithoutAdditionalRequests(): void
    {
        $movieCatalog = $this->catalog(movie: $this->details('movie', genres:[['id'=>16,'name'=>'Animação']], originalLanguage:'ja'));
        $seriesCatalog = $this->catalog(series: $this->details('series', genres:[['id'=>16,'name'=>'Animação']], originalLanguage:'ja'));
        $movie = $this->controller($movieCatalog)->movie('603');
        $series = $this->controller($seriesCatalog)->series('1396');

        self::assertStringContainsString('Anime · Filme · TMDB', $movie->body());
        self::assertStringContainsString('Anime · Série · TMDB', $series->body());
        self::assertSame([['movie',603],['configuration']], $movieCatalog->calls);
        self::assertSame([['series',1396],['configuration']], $seriesCatalog->calls);
    }

    public function testDetailsDoNotMislabelIncompleteOrSingleCriterionContent(): void
    {
        $liveAction = $this->catalog(movie: $this->details('movie', genres:[['id'=>18,'name'=>'Drama']], originalLanguage:'ja'));
        $westernAnimation = $this->catalog(movie: $this->details('movie', genres:[['id'=>16,'name'=>'Animação']], originalLanguage:'en'));
        self::assertStringNotContainsString('Anime · Filme', $this->controller($liveAction)->movie('603')->body());
        self::assertStringNotContainsString('Anime · Filme', $this->controller($westernAnimation)->movie('603')->body());
    }

    public function testInvalidIdsReturn404BeforeCallingTmdb(): void
    {
        foreach (['0', '-1', 'abc', '12.5', '1<script>', '2147483648', '99999999999'] as $id) {
            $catalog = $this->catalog();
            $response = $this->controller($catalog)->movie($id);
            self::assertSame(404, $response->status());
            self::assertSame([], $catalog->calls);
            self::assertStringContainsString('Título não encontrado', $response->body());
        }
    }

    public function testMapsTmdbErrorsToSafePublicStates(): void
    {
        foreach ([
            'not_found' => [404, 'Título não encontrado'],
            'not_configured' => [503, 'não está disponível neste ambiente'],
            'rate_limited' => [503, 'Muitas consultas foram realizadas'],
            'transport' => [503, 'Não foi possível consultar o catálogo'],
            'invalid_json' => [503, 'Não foi possível consultar o catálogo'],
            'unexpected_payload' => [503, 'Não foi possível consultar o catálogo'],
        ] as $category => [$status, $message]) {
            $catalog = $this->catalog();
            $catalog->exception = new TmdbException($category);
            $response = $this->controller($catalog)->movie('603');
            self::assertSame($status, $response->status());
            self::assertStringContainsString($message, $response->body());
            self::assertStringNotContainsString('TmdbException', $response->body());
            self::assertStringNotContainsString('Authorization', $response->body());
        }
    }

    public function testAdultDetailsReturnGeneric404WithoutLeakingContentOrImages(): void
    {
        $catalog = $this->catalog(movie: $this->details('movie', adult: true, title: 'Secret Title', overview: 'Secret Overview'));
        $response = $this->controller($catalog)->movie('603');

        self::assertSame(404, $response->status());
        self::assertSame([['movie', 603]], $catalog->calls);
        self::assertStringNotContainsString('Secret Title', $response->body());
        self::assertStringNotContainsString('Secret Overview', $response->body());
        self::assertStringNotContainsString('/poster.jpg', $response->body());
        self::assertStringNotContainsString('/backdrop.jpg', $response->body());
    }

    public function testDetailsWithoutImagesAvoidConfigurationAndUseFallbacks(): void
    {
        $catalog = $this->catalog(movie: $this->details('movie', posterPath: null, backdropPath: null));
        $response = $this->controller($catalog)->movie('603');

        self::assertSame([['movie', 603]], $catalog->calls);
        self::assertStringContainsString('media-detail__poster', $response->body());
        self::assertStringNotContainsString('<img src=', $response->body());
        self::assertStringContainsString('Matrix', $response->body());
    }

    public function testConfigurationFailureKeepsTrustedDetails(): void
    {
        $catalog = $this->catalog(movie: $this->details('movie'));
        $catalog->configurationException = new TmdbException('transport');
        $response = $this->controller($catalog)->movie('603');

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Matrix', $response->body());
        self::assertStringContainsString('Uma escolha muda tudo.', $response->body());
        self::assertStringNotContainsString('<img src=', $response->body());
    }

    public function testEscapesEveryExternalTextField(): void
    {
        $payload = '<script>alert(1)</script>';
        $catalog = $this->catalog(movie: new TmdbMediaDetails(
            'tmdb', 'movie', 603, $payload, $payload, $payload, $payload, null, null,
            null, null, [['id' => 1, 'name' => $payload]], 8.0, 1, null, 'en', false,
            $payload, 120, null, null, null, null,
        ));
        $response = $this->controller($catalog)->movie('603');

        self::assertStringNotContainsString($payload, $response->body());
        self::assertGreaterThanOrEqual(4, substr_count($response->body(), '&lt;script&gt;alert(1)&lt;/script&gt;'));
    }

    private function controller(TmdbCatalog $catalog): MediaDetailsController
    {
        return new MediaDetailsController(
            new View(dirname(__DIR__) . '/resources/views'),
            $catalog,
        );
    }

    private function details(
        string $type,
        bool $adult = false,
        string $title = '',
        string $overview = 'Uma escolha muda tudo.',
        ?string $posterPath = '/poster.jpg',
        ?string $backdropPath = '/backdrop.jpg',
        ?array $genres = null,
        ?string $originalLanguage = 'en',
    ): TmdbMediaDetails {
        $movie = $type === 'movie';
        return new TmdbMediaDetails(
            'tmdb',
            $type,
            $movie ? 603 : 1396,
            $title !== '' ? $title : ($movie ? 'Matrix' : 'Breaking Bad'),
            $movie ? 'The Matrix' : 'Breaking Bad',
            $movie ? 'Bem-vindo ao mundo real.' : 'All bad things must come to an end.',
            $overview,
            $movie ? '1999-03-31' : '2008-01-20',
            $movie ? 1999 : 2008,
            $posterPath,
            $backdropPath,
            $genres ?? [['id' => $movie ? 28 : 18, 'name' => $movie ? 'Ação' : 'Drama']],
            $movie ? 8.217 : 8.9,
            $movie ? 26789 : 14000,
            100.0,
            $originalLanguage,
            $adult,
            $movie ? 'Released' : 'Ended',
            $movie ? 136 : null,
            $movie ? null : 5,
            $movie ? null : 62,
            $movie ? null : '2013-09-29',
            $movie ? null : false,
        );
    }

    /** @return TmdbCatalog&object{calls:array,exception:?TmdbException,configurationException:?TmdbException} */
    private function catalog(?TmdbMediaDetails $movie = null, ?TmdbMediaDetails $series = null): TmdbCatalog
    {
        return new class($movie, $series) implements TmdbCatalog {
            public array $calls = [];
            public ?TmdbException $exception = null;
            public ?TmdbException $configurationException = null;

            public function __construct(
                private readonly ?TmdbMediaDetails $movie,
                private readonly ?TmdbMediaDetails $series,
            ) {
            }

            public function configured(): bool { return true; }

            public function movieDetails(int $id): TmdbMediaDetails
            {
                $this->calls[] = ['movie', $id];
                if ($this->exception !== null) { throw $this->exception; }
                return $this->movie ?? throw new TmdbException('not_found', 404);
            }

            public function seriesDetails(int $id): TmdbMediaDetails
            {
                $this->calls[] = ['series', $id];
                if ($this->exception !== null) { throw $this->exception; }
                return $this->series ?? throw new TmdbException('not_found', 404);
            }
            public function seasonDetails(int $seriesId,int $seasonNumber): \App\Integrations\Tmdb\TmdbSeasonDetails { throw new \LogicException('Not used.'); }

            public function configuration(): TmdbImageConfiguration
            {
                $this->calls[] = ['configuration'];
                if ($this->configurationException !== null) { throw $this->configurationException; }
                return new TmdbImageConfiguration('https://image.tmdb.org/t/p/', ['w500'], ['w1280']);
            }

            public function searchMovies(string $query, int $page = 1): array
            {
                throw new \LogicException('Not used by details tests.');
            }

            public function searchSeries(string $query, int $page = 1): array
            {
                throw new \LogicException('Not used by details tests.');
            }

            public function discoverAnimeMovies(int $page = 1): array { throw new \LogicException('Not used.'); }
            public function discoverAnimeSeries(int $page = 1): array { throw new \LogicException('Not used.'); }
        };
    }
}
