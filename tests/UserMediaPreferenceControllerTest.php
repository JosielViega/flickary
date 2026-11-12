<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\UserMediaPreferenceController;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageConfiguration;
use App\Integrations\Tmdb\TmdbMediaDetails;
use App\Preferences\UserMediaPreference;
use App\Preferences\UserMediaPreferenceDraft;
use App\Preferences\UserMediaPreferencePage;
use App\Preferences\UserMediaPreferenceStore;
use PHPUnit\Framework\TestCase;

final class UserMediaPreferenceControllerTest extends TestCase
{
    protected function setUp(): void { $_SESSION=[]; }

    public function testVisitorAndInvalidRequestsNeverMutateOrCallTmdb(): void
    {
        [$visitor,$store,$catalog]=$this->fixture(false);
        self::assertSame(303,$visitor->save(new Request(),'603','movie')->status());
        self::assertSame(303,$visitor->favorites(new Request())->status());
        self::assertSame([], $store->calls);self::assertSame([], $catalog->calls);

        [$controller,$store,$catalog,$csrf]=$this->fixture();
        self::assertSame(404,$controller->save(new Request(),'bad','movie')->status());
        self::assertSame(403,$controller->save(new Request(parsedBody:['rating'=>'9']),'603','movie')->status());
        self::assertSame(422,$controller->save(new Request(parsedBody:['_token'=>$csrf->token(),'rating'=>'7.5']),'603','movie')->status());
        self::assertSame([], $store->calls);self::assertSame([], $catalog->calls);
    }

    public function testFirstMoviePreferenceUsesServerSnapshotAndIgnoresTampering(): void
    {
        [$controller,$store,$catalog,$csrf]=$this->fixture();
        $catalog->details=$this->details('movie',true);
        $response=$controller->save(new Request(parsedBody:[
            '_token'=>$csrf->token(),'favorite'=>'1','rating'=>'9','user_id'=>'999','source'=>'anilist',
            'media_type'=>'anime','title'=>'Hack','poster_path'=>'/fake.jpg','is_anime'=>'0',
        ]),'1311031','movie');
        self::assertSame(303,$response->status());self::assertSame('/filmes/1311031',$response->headers()['Location']);
        self::assertSame([['movie',1311031]],$catalog->calls);
        self::assertSame(7,$store->createdUser);self::assertSame('tmdb',$store->created?->source);self::assertSame('movie',$store->created?->mediaType);
        self::assertSame('Server title',$store->created?->title);self::assertSame('/server.jpg',$store->created?->posterPath);self::assertTrue($store->created?->isAnime);
        self::assertTrue($store->created?->isFavorite);self::assertSame(9,$store->created?->rating);
    }

    public function testFirstSeriesRatingOnlyDoesNotRequireFavorite(): void
    {
        [$controller,$store,$catalog,$csrf]=$this->fixture();$catalog->details=$this->details('series',false);
        $response=$controller->save(new Request(parsedBody:['_token'=>$csrf->token(),'rating'=>'10']),'1396','series');
        self::assertSame(303,$response->status());self::assertSame('/series/1396',$response->headers()['Location']);
        self::assertFalse($store->created?->isFavorite);self::assertSame(10,$store->created?->rating);self::assertFalse($store->created?->isAnime);
    }

    public function testExistingPreferenceUpdatesAndClearsLocallyWithoutChangingSnapshot(): void
    {
        [$controller,$store,$catalog,$csrf]=$this->fixture();
        $store->existing=$this->preference(false,8,'Frozen title');$catalog->fail=true;
        $controller->save(new Request(parsedBody:['_token'=>$csrf->token(),'favorite'=>'1','rating'=>'10']),'603','movie');
        self::assertSame([], $catalog->calls);self::assertSame([true,10],$store->updated);self::assertSame('Frozen title',$store->existing->title);
        $controller->save(new Request(parsedBody:['_token'=>$csrf->token(),'rating'=>'']),'603','movie');
        self::assertTrue($store->deleted);self::assertSame([], $catalog->calls);
    }

    public function testEmptyWithoutRowIsNoOpAndAdultOrTmdbFailureCreateNothing(): void
    {
        [$controller,$store,$catalog,$csrf]=$this->fixture();
        self::assertSame(303,$controller->save(new Request(parsedBody:['_token'=>$csrf->token(),'rating'=>'']),'603','movie')->status());
        self::assertNull($store->created);self::assertSame([], $catalog->calls);
        $catalog->details=$this->details('movie',false,true);
        self::assertSame(404,$controller->save(new Request(parsedBody:['_token'=>$csrf->token(),'favorite'=>'1']),'603','movie')->status());
        self::assertNull($store->created);
        $catalog->exception=new TmdbException('transport');
        self::assertSame(303,$controller->save(new Request(parsedBody:['_token'=>$csrf->token(),'favorite'=>'1']),'603','movie')->status());
        self::assertNull($store->created);
    }

    public function testPrivateIndexesApplyLocalFiltersAndSurviveImageConfigurationFailure(): void
    {
        [$controller,$store,$catalog]=$this->fixture();$item=$this->preference(true,9,'<script>bad</script>',true);$store->page=new UserMediaPreferencePage([$item],1,1,24);$catalog->exception=new TmdbException('transport');
        $favorite=$controller->favorites(new Request(queryParams:['tipo'=>'anime']));
        self::assertTrue($store->lastAnimeOnly);
        $ratings=$controller->ratings(new Request(queryParams:['tipo'=>'filmes']));
        self::assertSame(200,$favorite->status());self::assertSame(200,$ratings->status());
        self::assertStringContainsString('&lt;script&gt;bad&lt;/script&gt;',$favorite->body());self::assertStringContainsString('Sua nota 9/10',$ratings->body());
        self::assertSame([['configuration'],['configuration']],$catalog->calls);
    }

    private function fixture(bool$authenticated=true):array
    {
        $session=new Session(false);$auth=new Auth($session);if($authenticated)$auth->login(7);$csrf=new Csrf($session);$store=new PreferenceMemoryStore();$catalog=new PreferenceCatalog($this->details('movie',false));
        return [new UserMediaPreferenceController(new View(dirname(__DIR__).'/resources/views'),$auth,$csrf,$session,$store,$catalog),$store,$catalog,$csrf];
    }
    private function details(string$type,bool$anime=false,bool$adult=false):TmdbMediaDetails
    {
        $id=$type==='movie'?1311031:1396;return new TmdbMediaDetails('tmdb',$type,$id,'Server title','Original',null,null,'2025-01-01',2025,'/server.jpg',null,$anime?[['id'=>16,'name'=>'Animation']]:[['id'=>18,'name'=>'Drama']],8.0,10,null,$anime?'ja':'en',$adult,null,null,null,null,null,null);
    }
    private function preference(bool$favorite,?int$rating,string$title='Title',?bool$anime=false):UserMediaPreference{return new UserMediaPreference(1,7,'tmdb','movie',603,$title,'Original','1999-03-31','/poster.jpg',$anime,$favorite,$rating,'2026-01-01','2026-01-01');}
}

final class PreferenceMemoryStore implements UserMediaPreferenceStore
{
    public array$calls=[];public ?UserMediaPreference$existing=null;public ?UserMediaPreferenceDraft$created=null;public ?int$createdUser=null;public ?array$updated=null;public bool$deleted=false;public bool$createResult=true;public ?UserMediaPreferencePage$page=null;public bool$lastAnimeOnly=false;
    public function findForUser(int$u,string$s,string$t,int$i):?UserMediaPreference{$this->calls[]=['find'];return$this->existing;}
    public function create(int$u,UserMediaPreferenceDraft$d):bool{$this->createdUser=$u;$this->created=$d;$this->calls[]=['create'];return$this->createResult;}
    public function update(int$u,string$s,string$t,int$i,bool$f,?int$r):bool{$this->updated=[$f,$r];$this->calls[]=['update'];return true;}
    public function delete(int$u,string$s,string$t,int$i):bool{$this->deleted=true;$this->calls[]=['delete'];return true;}
    public function paginateFavorites(int$u,?string$t,bool$a,int$p,int$pp):UserMediaPreferencePage{$this->lastAnimeOnly=$a;return$this->page??new UserMediaPreferencePage([],0,$p,$pp);}
    public function paginateRated(int$u,?string$t,bool$a,int$p,int$pp):UserMediaPreferencePage{$this->lastAnimeOnly=$a;return$this->page??new UserMediaPreferencePage([],0,$p,$pp);}
    public function countFavorites(int$u):int{return$this->page?->total??0;}public function countRated(int$u):int{return$this->page?->total??0;}
}

final class PreferenceCatalog implements TmdbCatalog
{
    public array$calls=[];public bool$fail=false;public ?TmdbException$exception=null;public function __construct(public TmdbMediaDetails$details){}
    public function configured():bool{return true;}public function movieDetails(int$id):TmdbMediaDetails{$this->calls[]=['movie',$id];if($this->fail)throw new \LogicException();if($this->exception)throw$this->exception;return$this->details;}public function seriesDetails(int$id):TmdbMediaDetails{$this->calls[]=['series',$id];if($this->fail)throw new \LogicException();if($this->exception)throw$this->exception;return$this->details;}
    public function configuration():TmdbImageConfiguration{$this->calls[]=['configuration'];if($this->exception)throw$this->exception;return new TmdbImageConfiguration('https://image.tmdb.org/t/p/',['w500'],['w1280']);}
    public function seasonDetails(int$i,int$s):\App\Integrations\Tmdb\TmdbSeasonDetails{throw new \LogicException();}public function searchMovies(string$q,int$p=1):array{return[];}public function searchSeries(string$q,int$p=1):array{return[];}public function discoverAnimeMovies(int$p=1):array{return[];}public function discoverAnimeSeries(int$p=1):array{return[];}
}
