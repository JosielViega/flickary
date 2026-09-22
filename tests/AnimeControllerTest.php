<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\AnimeController;
use App\Core\Request;
use App\Core\View;
use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageConfiguration;
use App\Integrations\Tmdb\TmdbMedia;
use App\Integrations\Tmdb\TmdbMediaDetails;
use App\Integrations\Tmdb\TmdbSeasonDetails;
use PHPUnit\Framework\TestCase;

final class AnimeControllerTest extends TestCase
{
    public function testPublicAllViewUsesTwoDiscoversOneConfigurationAndEightItemSummaries(): void
    {
        $series = array_fill(0, 9, $this->media('series'));
        $movies = array_fill(0, 9, $this->media('movie'));
        $catalog = new AnimeCatalog($movies, $series);
        $response = $this->controller($catalog)->index(new Request());

        self::assertSame(200, $response->status());
        self::assertSame([['series',1],['movie',1],['configuration']], $catalog->calls);
        self::assertSame(16, substr_count($response->body(), '<article class="media-card">'));
        self::assertStringContainsString('Séries Anime', $response->body());
        self::assertStringContainsString('Filmes Anime', $response->body());
        self::assertStringContainsString('href="/anime?tipo=series"', $response->body());
        self::assertStringContainsString('href="/anime?tipo=filmes"', $response->body());
        self::assertStringContainsString('Anime · Série', $response->body());
        self::assertStringContainsString('Anime · Filme', $response->body());
        self::assertStringContainsString('href="/series/46260"', $response->body());
        self::assertStringContainsString('href="/filmes/129"', $response->body());
    }

    public function testSeriesAndMoviesFiltersHaveIndependentRealPagination(): void
    {
        $seriesCatalog = new AnimeCatalog([], [$this->media('series')], seriesPages: 4);
        $series = $this->controller($seriesCatalog)->index(new Request(queryParams:['tipo'=>'series','page'=>'2']));
        self::assertSame([['series',2],['configuration']], $seriesCatalog->calls);
        self::assertStringContainsString('Página 2 de 4', $series->body());
        self::assertStringContainsString('/anime?tipo=series&amp;page=3', $series->body());

        $movieCatalog = new AnimeCatalog([$this->media('movie')], [], moviePages: 3);
        $movies = $this->controller($movieCatalog)->index(new Request(queryParams:['tipo'=>'filmes','page'=>'2']));
        self::assertSame([['movie',2],['configuration']], $movieCatalog->calls);
        self::assertStringContainsString('Página 2 de 3', $movies->body());
    }

    public function testInvalidTypeAndPageDegradeSafely(): void
    {
        $catalog = new AnimeCatalog([], []);
        $response = $this->controller($catalog)->index(new Request(queryParams:['tipo'=>'qualquer','page'=>'-9']));
        self::assertSame(200, $response->status());
        self::assertSame([['series',1],['movie',1]], $catalog->calls);
        self::assertStringContainsString('href="/anime?tipo=todos" class="search-filter is-active"', $response->body());
    }

    public function testDefensiveClassifierDropsInconsistentProviderItemsWithoutChangingTotals(): void
    {
        $catalog = new AnimeCatalog([
            $this->media('movie'),
            $this->media('movie', [16], 'en', 130, 'Western animation'),
            $this->media('movie', [18], 'ja', 131, 'Japanese live action'),
        ], [], moviePages: 7, movieTotal: 123);
        $response = $this->controller($catalog)->index(new Request(queryParams:['tipo'=>'filmes']));

        self::assertSame(1, substr_count($response->body(), '<article class="media-card">'));
        self::assertStringNotContainsString('Western animation', $response->body());
        self::assertStringNotContainsString('Japanese live action', $response->body());
        self::assertStringContainsString('Página 1 de 7', $response->body());
    }

    public function testFriendlyErrorsAndConfigurationFailureKeepSafeTextResults(): void
    {
        $errorCatalog = new AnimeCatalog([], []);
        $errorCatalog->movieException = new TmdbException('not_configured');
        $error = $this->controller($errorCatalog)->index(new Request(queryParams:['tipo'=>'filmes']));
        self::assertStringContainsString('não está disponível neste ambiente', $error->body());
        self::assertStringNotContainsString('Authorization', $error->body());

        $imageCatalog = new AnimeCatalog([$this->media('movie')], []);
        $imageCatalog->configurationException = new TmdbException('transport');
        $body = $this->controller($imageCatalog)->index(new Request(queryParams:['tipo'=>'filmes']))->body();
        self::assertStringContainsString('A Viagem de Chihiro', $body);
        self::assertStringNotContainsString('<img ', $body);
    }

    private function controller(TmdbCatalog $catalog): AnimeController
    {
        return new AnimeController(new View(dirname(__DIR__).'/resources/views'), $catalog);
    }

    private function media(string $type, array $genres=[16], ?string $language='ja', ?int $id=null, ?string $title=null): TmdbMedia
    {
        $movie=$type==='movie';
        return new TmdbMedia('tmdb',$type,$id??($movie?129:46260),$title??($movie?'A Viagem de Chihiro':'Naruto'),null,'Resumo',$movie?'2001-07-20':'2002-10-03',$movie?2001:2002,'/poster.jpg',null,8.5,100,50.0,$genres,$language,false);
    }
}

final class AnimeCatalog implements TmdbCatalog
{
    public array $calls=[];
    public ?TmdbException $movieException=null;
    public ?TmdbException $seriesException=null;
    public ?TmdbException $configurationException=null;
    public function __construct(private array $movies,private array $series,private int $moviePages=1,private int $seriesPages=1,private ?int $movieTotal=null,private ?int $seriesTotal=null){}
    public function configured():bool{return true;}
    public function configuration():TmdbImageConfiguration{$this->calls[]=['configuration'];if($this->configurationException)throw $this->configurationException;return new TmdbImageConfiguration('https://image.tmdb.org/t/p/',['w500'],['w1280']);}
    public function movieDetails(int$id):TmdbMediaDetails{throw new \LogicException();}
    public function seriesDetails(int$id):TmdbMediaDetails{throw new \LogicException();}
    public function seasonDetails(int$id,int$season):TmdbSeasonDetails{throw new \LogicException();}
    public function searchMovies(string$query,int$page=1):array{throw new \LogicException();}
    public function searchSeries(string$query,int$page=1):array{throw new \LogicException();}
    public function discoverAnimeMovies(int$page=1):array{$this->calls[]=['movie',$page];if($this->movieException)throw $this->movieException;return['page'=>$page,'total_pages'=>$this->moviePages,'total_results'=>$this->movieTotal??count($this->movies),'results'=>$this->movies];}
    public function discoverAnimeSeries(int$page=1):array{$this->calls[]=['series',$page];if($this->seriesException)throw $this->seriesException;return['page'=>$page,'total_pages'=>$this->seriesPages,'total_results'=>$this->seriesTotal??count($this->series),'results'=>$this->series];}
}
