<?php

declare(strict_types=1);

namespace Tests;

use App\Discovery\ContentDiscoveryService;
use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbDiscoveryCatalog;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageConfiguration;
use App\Integrations\Tmdb\TmdbMedia;
use App\Integrations\Tmdb\TmdbMediaDetails;
use App\Integrations\Tmdb\TmdbSeasonDetails;
use PHPUnit\Framework\TestCase;

final class ContentDiscoveryServiceTest extends TestCase
{
    public function testDefaultShelvesHaveRequiredOrderDynamicYearAndSingleConfiguration(): void
    {
        $catalog=new DiscoveryCatalogFake();
        $page=$this->service($catalog)->page('todos');
        self::assertSame(['Em alta agora','Lançamentos no cinema','Populares','Melhores de 2028','Animes em destaque','Em breve'],array_map(static fn($s)=>$s->title,$page->shelves));
        self::assertTrue($page->available());
        self::assertSame(1,count(array_filter($catalog->calls,static fn($call)=>$call==='configuration')));
        self::assertSame('https://image.tmdb.test/t/p/w342/1.jpg',$page->posterUrl('movie',1));
    }

    public function testMovieSeriesAndAnimeFiltersBuildOnlyTheirShelves(): void
    {
        $movies=$this->service(new DiscoveryCatalogFake())->page('filmes');
        self::assertSame(['Em alta nos filmes','Em cartaz','Filmes populares','Melhores filmes de 2028','Mais bem avaliados','Em breve'],array_map(static fn($s)=>$s->title,$movies->shelves));
        $series=$this->service(new DiscoveryCatalogFake())->page('series');
        self::assertSame(['Séries em alta','Séries populares','Melhores séries de 2028','Mais bem avaliadas'],array_map(static fn($s)=>$s->title,$series->shelves));
        $anime=$this->service(new DiscoveryCatalogFake())->page('anime');
        self::assertSame(['Animes em destaque','Séries Anime','Filmes Anime','Melhores Animes de 2028'],array_map(static fn($s)=>$s->title,$anime->shelves));
        self::assertSame('/anime',$anime->shelves[0]->ctaUrl);
    }

    public function testInterleaveDedupeAdultFilterAnimeDefenseAndLimit(): void
    {
        $catalog=new DiscoveryCatalogFake();
        $catalog->movies=[]; $catalog->series=[];
        for($i=1;$i<=9;$i++){ $catalog->movies[]=$catalog->media('movie',$i); $catalog->series[]=$catalog->media('series',100+$i); }
        $catalog->movies[]=$catalog->movies[0];
        $catalog->movies[]=$catalog->media('movie',99,adult:true);
        $page=$this->service($catalog)->page('todos');
        $popular=$page->shelves[2]->items;
        self::assertCount(12,$popular);
        self::assertSame([1,101,2,102,3,103],array_map(static fn(TmdbMedia$m)=>$m->sourceId,array_slice($popular,0,6)));
        self::assertNotContains(99,array_map(static fn(TmdbMedia$m)=>$m->sourceId,$popular));

        $catalog->animeMovies=[$catalog->media('movie',501,anime:true),$catalog->media('movie',502)];
        $anime=$this->service($catalog)->page('anime');
        self::assertSame([501],array_map(static fn(TmdbMedia$m)=>$m->sourceId,$anime->shelves[2]->items));
    }

    public function testShelfFailureIsIsolatedAndTotalFailureIsSafe(): void
    {
        $catalog=new DiscoveryCatalogFake(); $catalog->fail=['upcoming'];
        $page=$this->service($catalog)->page('todos');
        self::assertTrue($page->available()); self::assertSame([],$page->shelves[5]->items); self::assertSame('transport',$page->shelves[5]->error);

        $catalog=new DiscoveryCatalogFake();
        $catalog->fail=['trendingAll','now','popularMovies','popularSeries','bestMovies','bestSeries','animeSeries','animeMovies','upcoming'];
        self::assertFalse($this->service($catalog)->page('todos')->available());
    }

    private function service(DiscoveryCatalogFake $catalog): ContentDiscoveryService
    {
        return new ContentDiscoveryService($catalog,$catalog,now:static fn()=>new \DateTimeImmutable('2028-03-01 10:00:00'));
    }
}

final class DiscoveryCatalogFake implements TmdbCatalog,TmdbDiscoveryCatalog
{
    public array $calls=[]; public array $fail=[]; public array $movies; public array $series; public array $animeMovies; public array $animeSeries;
    public function __construct(){
        $this->movies=[$this->media('movie',1),$this->media('movie',2)];
        $this->series=[$this->media('series',101),$this->media('series',102)];
        $this->animeMovies=[$this->media('movie',201,anime:true)];
        $this->animeSeries=[$this->media('series',301,anime:true)];
    }
    public function configured():bool{return true;}
    public function configuration():TmdbImageConfiguration{$this->call('configuration');return new TmdbImageConfiguration('https://image.tmdb.test/t/p/',['w342','w500'],['w780']);}
    public function movieDetails(int$id):TmdbMediaDetails{throw new \LogicException();} public function seriesDetails(int$id):TmdbMediaDetails{throw new \LogicException();} public function seasonDetails(int$id,int$season):TmdbSeasonDetails{throw new \LogicException();}
    public function searchMovies(string$q,int$page=1):array{$this->call('searchMovies');return$this->page($this->movies);} public function searchSeries(string$q,int$page=1):array{$this->call('searchSeries');return$this->page($this->series);}
    public function discoverAnimeMovies(int$page=1):array{$this->call('animeMovies');return$this->page($this->animeMovies);} public function discoverAnimeSeries(int$page=1):array{$this->call('animeSeries');return$this->page($this->animeSeries);}
    public function trendingAll():array{$this->call('trendingAll');return$this->page([$this->movies[0],$this->series[0]]);} public function trendingMovies():array{$this->call('trendingMovies');return$this->page($this->movies);} public function trendingSeries():array{$this->call('trendingSeries');return$this->page($this->series);}
    public function nowPlayingMovies():array{$this->call('now');return$this->page($this->movies);} public function upcomingMovies():array{$this->call('upcoming');return$this->page($this->movies);}
    public function popularMovies():array{$this->call('popularMovies');return$this->page($this->movies);} public function popularSeries():array{$this->call('popularSeries');return$this->page($this->series);}
    public function topRatedMovies():array{$this->call('topMovies');return$this->page($this->movies);} public function topRatedSeries():array{$this->call('topSeries');return$this->page($this->series);}
    public function bestMoviesOfYear(int$year):array{$this->call('bestMovies');return$this->page($this->movies);} public function bestSeriesOfYear(int$year):array{$this->call('bestSeries');return$this->page($this->series);}
    public function bestAnimeMoviesOfYear(int$year):array{$this->call('bestAnimeMovies');return$this->page($this->animeMovies);} public function bestAnimeSeriesOfYear(int$year):array{$this->call('bestAnimeSeries');return$this->page($this->animeSeries);}
    public function media(string$type,int$id,bool$anime=false,bool$adult=false):TmdbMedia{return new TmdbMedia('tmdb',$type,$id,'Título '.$id,null,'Resumo','2028-01-01',2028,'/'.$id.'.jpg',null,8.2,500,50.0,$anime?[16]:[18],$anime?'ja':'en',$adult);}
    private function page(array$results):array{return['page'=>1,'total_pages'=>1,'total_results'=>count($results),'results'=>$results];}
    private function call(string$name):void{$this->calls[]=$name;if(in_array($name,$this->fail,true))throw new TmdbException('transport');}
}
