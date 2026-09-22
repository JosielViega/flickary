<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\UserScheduleController;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbEpisode;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageConfiguration;
use App\Integrations\Tmdb\TmdbMediaDetails;
use App\Integrations\Tmdb\TmdbSeasonDetails;
use App\Schedule\ScheduleEntry;
use App\Schedule\ScheduleItem;
use App\Schedule\SchedulePage;
use App\Schedule\ScheduleStore;
use PHPUnit\Framework\TestCase;

final class UserScheduleControllerTest extends TestCase
{
    protected function setUp(): void { $_SESSION = []; }

    public function testVisitorRedirectsAndCsrfAndDateAreRequired(): void
    {
        [$controller] = $this->fixture(false);
        self::assertSame(303, $controller->index(new Request())->status());
        self::assertSame(303, $controller->createMedia(new Request(), '603', 'movie')->status());
        [$controller,,, $csrf] = $this->fixture(true);
        self::assertSame(403, $controller->createMedia(new Request(parsedBody: ['scheduled_on' => date('Y-m-d')]), '603', 'movie')->status());
        self::assertSame(422, $controller->createMedia(new Request(parsedBody: ['_token'=>$csrf->token(),'scheduled_on'=>'2020-01-01']), '603', 'movie')->status());
    }

    public function testNewMovieUsesServerSnapshotAndExistingRescheduleIsOffline(): void
    {
        [$controller,$store,$catalog,$csrf] = $this->fixture(true);
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $request = new Request(parsedBody: ['_token'=>$csrf->token(),'scheduled_on'=>$tomorrow,'title'=>'Fake','user_id'=>'999','source'=>'evil']);
        self::assertSame(303, $controller->createMedia($request, '603', 'movie')->status());
        self::assertSame('Matrix', $store->created?->title);
        self::assertSame('tmdb', $store->created?->source);
        self::assertSame([['movie',603]], $catalog->calls);
        $store->existing = $store->entry('movie', 603, 0, 0, date('Y-m-d'));
        $catalog->fail = true; $catalog->calls = [];
        self::assertSame(303, $controller->createMedia($request, '603', 'movie')->status());
        self::assertSame([], $catalog->calls);
        self::assertSame($tomorrow, $store->updatedDate);
    }

    public function testEpisodeCreationValidatesSeasonAndSeriesWithoutRequiringList(): void
    {
        [$controller,$store,$catalog,$csrf] = $this->fixture(true);
        $request = new Request(parsedBody: ['_token'=>$csrf->token(),'scheduled_on'=>date('Y-m-d'),'episode_title'=>'Fake']);
        self::assertSame(303, $controller->createEpisode($request, '1396', '0', '1')->status());
        self::assertSame('episode', $store->created?->entryType);
        self::assertSame(0, $store->created?->seasonNumber);
        self::assertSame('Piloto', $store->created?->episodeTitle);
        self::assertSame([['season',1396,0],['series',1396]], $catalog->calls);
    }

    public function testAgendaEscapesSnapshotsAndUsesOnlyConfiguration(): void
    {
        [$controller,$store,$catalog] = $this->fixture(true);
        $store->items = [$store->entry('movie',603,0,0,date('Y-m-d'),'<script>alert(1)</script>')];
        $response = $controller->index(new Request());
        self::assertSame(200, $response->status());
        self::assertStringNotContainsString('<script>alert(1)</script>', $response->body());
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $response->body());
        self::assertSame([], $catalog->calls, 'No poster path means zero TMDB calls.');
    }

    public function testPersistedAgendaUpdateAndRemovalRemainAvailableWithoutTmdb(): void
    {
        [$controller,$store,$catalog,$csrf] = $this->fixture(true);
        $store->existing = $store->entry('series',1396,0,0,date('Y-m-d'),'Breaking Bad','/poster.jpg');
        $store->items = [$store->existing]; $catalog->fail = true;
        $index = $controller->index(new Request());
        self::assertSame(200, $index->status());
        self::assertStringContainsString('Breaking Bad', $index->body());
        self::assertSame([['configuration']], $catalog->calls);
        $catalog->calls = [];
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        self::assertSame(303, $controller->update(new Request(parsedBody:['_token'=>$csrf->token(),'scheduled_on'=>$tomorrow]), '11')->status());
        self::assertSame($tomorrow, $store->updatedDate);
        self::assertSame([], $catalog->calls);
        self::assertSame(303, $controller->remove(new Request(parsedBody:['_token'=>$csrf->token()]), '11')->status());
        self::assertTrue($store->deleted);
        self::assertSame([], $catalog->calls);
    }

    public function testFirstCreationFailureDoesNotPersistPartialItem(): void
    {
        [$controller,$store,$catalog,$csrf] = $this->fixture(true); $catalog->fail = true;
        $response = $controller->createMedia(new Request(parsedBody:['_token'=>$csrf->token(),'scheduled_on'=>date('Y-m-d')]),'603','movie');
        self::assertSame(303,$response->status()); self::assertNull($store->created);
    }

    private function fixture(bool $logged): array
    {
        $session = new Session(false); $auth = new Auth($session); if ($logged) { $auth->login(7); }
        $csrf = new Csrf($session); $store = new ScheduleMemoryStore(); $catalog = new ScheduleCatalog();
        return [new UserScheduleController(new View(dirname(__DIR__).'/resources/views'),$auth,$csrf,$session,$store,$catalog),$store,$catalog,$csrf];
    }
}

final class ScheduleMemoryStore implements ScheduleStore
{
    public ?ScheduleEntry $existing=null; public ?ScheduleItem $created=null; public ?string $updatedDate=null; public bool $deleted=false; public array $items=[];
    public function entry(string$type,int$id,int$season,int$episode,string$date,string$title='Matrix',?string$poster=null):ScheduleEntry{return new ScheduleEntry(11,7,'tmdb',$type,$id,$season,$episode,$title,null,$type==='episode'?'Piloto':null,null,$poster,$date,'','');}
    public function findForUserIdentity(int$userId,string$source,string$entryType,int$sourceId,int$seasonNumber=0,int$episodeNumber=0):?ScheduleEntry{return$this->existing;}
    public function findForUser(int$userId,int$id):?ScheduleEntry{return$this->existing;}
    public function create(int$userId,ScheduleItem$item):bool{$this->created=$item;return true;}
    public function updateDate(int$userId,int$id,string$scheduledOn):bool{$this->updatedDate=$scheduledOn;return true;}
    public function delete(int$userId,int$id):bool{$this->deleted=true;return true;}
    public function paginateForUser(int$userId,?string$entryType,string$today,int$page,int$perPage):SchedulePage{return new SchedulePage($this->items,count($this->items),$page,$perPage);}
    public function countForUser(int$userId):int{return count($this->items);}
    public function episodeSchedulesForSeason(int$userId,string$source,int$seriesId,int$seasonNumber):array{return[];}
}

final class ScheduleCatalog implements TmdbCatalog
{
    public array $calls=[]; public bool $fail=false;
    public function configured():bool{return true;}
    public function movieDetails(int$id):TmdbMediaDetails{$this->calls[]=['movie',$id];if($this->fail)throw new TmdbException('transport');return $this->details('movie',$id);}
    public function seriesDetails(int$id):TmdbMediaDetails{$this->calls[]=['series',$id];if($this->fail)throw new TmdbException('transport');return $this->details('series',$id);}
    public function seasonDetails(int$seriesId,int$seasonNumber):TmdbSeasonDetails{$this->calls[]=['season',$seriesId,$seasonNumber];if($this->fail)throw new TmdbException('transport');return new TmdbSeasonDetails('tmdb',$seriesId,1,$seasonNumber,'Especiais',null,null,null,[new TmdbEpisode(1,$seasonNumber,1,'Piloto',null,'2008-01-20',50,null)]);}
    public function configuration():TmdbImageConfiguration{$this->calls[]=['configuration'];if($this->fail)throw new TmdbException('transport');return new TmdbImageConfiguration('https://image.tmdb.org/t/p/',['w500'],['w1280']);}
    public function searchMovies(string$q,int$p=1):array{return[];} public function searchSeries(string$q,int$p=1):array{return[];} public function discoverAnimeMovies(int$p=1):array{return[];} public function discoverAnimeSeries(int$p=1):array{return[];}
    private function details(string$type,int$id):TmdbMediaDetails{$movie=$type==='movie';return new TmdbMediaDetails('tmdb',$type,$id,$movie?'Matrix':'Breaking Bad',$movie?'The Matrix':'Breaking Bad',null,null,$movie?'1999-03-31':'2008-01-20',$movie?1999:2008,null,null,[],null,null,null,'en',false,null,$movie?136:null,$movie?null:5,$movie?null:62,null,false);}
}
