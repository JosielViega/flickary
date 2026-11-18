<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\SearchController;
use App\Core\Request;
use App\Core\View;
use App\Discovery\ContentDiscoveryService;
use PHPUnit\Framework\TestCase;

final class SearchDiscoveryControllerTest extends TestCase
{
    public function testEmptyQueryRendersDiscoveryTabsShelvesCompactCardsAndNoAutofocus(): void
    {
        $catalog=new DiscoveryCatalogFake();
        $response=$this->controller($catalog)->index(new Request());
        $body=$response->body();
        self::assertSame(200,$response->status());
        self::assertStringContainsString('Descoberta de conteúdo',$body);
        self::assertStringContainsString('Em alta agora',$body);
        self::assertStringContainsString('Lançamentos no cinema',$body);
        self::assertStringContainsString('Melhores de 2028',$body);
        self::assertStringContainsString('class="discovery-row"',$body);
        self::assertStringContainsString('class="discovery-card"',$body);
        self::assertStringContainsString('width="342" height="513"',$body);
        self::assertStringNotContainsString('autofocus',$body);
        self::assertStringContainsString('descobrir=anime',$body);
    }

    public function testDiscoveryFilterIsAllowlistedAndAnimeCardsUseRealRoutesAndLabels(): void
    {
        $catalog=new DiscoveryCatalogFake();
        $body=$this->controller($catalog)->index(new Request(queryParams:['descobrir'=>'anime']))->body();
        self::assertStringContainsString('Melhores Animes de 2028',$body);
        self::assertStringContainsString('Anime · Filme',$body);
        self::assertStringContainsString('Anime · Série',$body);
        self::assertStringContainsString('href="/filmes/201"',$body);
        self::assertStringContainsString('href="/series/301"',$body);

        $invalid=$this->controller(new DiscoveryCatalogFake())->index(new Request(queryParams:['descobrir'=>'../../']))->body();
        self::assertStringContainsString('descobrir=todos" class="search-filter is-active"',$invalid);
    }

    public function testTextSearchIgnoresDiscoveryParameterAndDoesNotBuildShelves(): void
    {
        $catalog=new DiscoveryCatalogFake();
        $response=$this->controller($catalog)->index(new Request(queryParams:['q'=>'matrix','tipo'=>'filmes','descobrir'=>'anime']));
        self::assertSame(['searchMovies','configuration'],$catalog->calls);
        self::assertStringContainsString('Resultados para',$response->body());
        self::assertStringNotContainsString('Descoberta de conteúdo',$response->body());
    }

    public function testDiscoveryEscapesProviderTitles(): void
    {
        $catalog=new DiscoveryCatalogFake();
        $catalog->movies=[$catalog->media('movie',1)];
        $catalog->movies=[new \App\Integrations\Tmdb\TmdbMedia('tmdb','movie',1,'<script>alert(1)</script>',null,null, null,null,null,null,8.0,100,1.0,[18],'en',false)];
        $body=$this->controller($catalog)->index(new Request(queryParams:['descobrir'=>'filmes']))->body();
        self::assertStringNotContainsString('<script>alert(1)</script>',$body);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;',$body);
    }

    private function controller(DiscoveryCatalogFake $catalog): SearchController
    {
        $service=new ContentDiscoveryService($catalog,$catalog,now:static fn()=>new \DateTimeImmutable('2028-01-01'));
        return new SearchController(new View(dirname(__DIR__).'/resources/views'),$catalog,discovery:$service);
    }
}
