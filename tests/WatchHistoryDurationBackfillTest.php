<?php

declare(strict_types=1);

namespace Tests;

use App\History\WatchHistoryDurationBackfill;
use App\History\WatchHistoryDurationStore;
use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbEpisode;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageConfiguration;
use App\Integrations\Tmdb\TmdbMediaDetails;
use App\Integrations\Tmdb\TmdbSeasonDetails;
use PHPUnit\Framework\TestCase;

final class WatchHistoryDurationBackfillTest extends TestCase
{
    public function testGroupsRewatchesAndEpisodesAndIsIdempotent(): void
    {
        $store = new DurationMemoryStore(); $catalog = new DurationCatalog();
        $first = (new WatchHistoryDurationBackfill($store, $catalog))->run();
        self::assertSame(6, $first->initiallyMissing);
        self::assertSame(4, $first->filled);
        self::assertSame(2, $first->remaining);
        self::assertSame(1, $first->moviesRequested);
        self::assertSame(2, $first->seasonsRequested);
        self::assertSame([['movie',603],['season',1396,1],['season',1396,2]], $catalog->calls);
        self::assertSame(136, $store->rows[0]['duration']); self::assertSame(136, $store->rows[1]['duration']);
        self::assertSame(47, $store->rows[2]['duration']); self::assertNull($store->rows[3]['duration']);
        $catalog->calls=[]; $second=(new WatchHistoryDurationBackfill($store,$catalog))->run();
        self::assertSame(2,$second->initiallyMissing); self::assertSame(0,$second->filled);
        self::assertSame([['season',1396,1]],$catalog->calls);
    }

    public function testNotConfiguredChangesNothingAndSevereErrorsStopWithoutRetry(): void
    {
        $store=new DurationMemoryStore();$catalog=new DurationCatalog();$catalog->configured=false;
        try{(new WatchHistoryDurationBackfill($store,$catalog))->run();self::fail('Expected failure.');}catch(\RuntimeException $e){self::assertStringContainsString('not configured',$e->getMessage());}
        self::assertSame(6,$store->countMissingDuration());
        $catalog->configured=true;$catalog->failure=new TmdbException('rate_limited',429);
        $result=(new WatchHistoryDurationBackfill($store,$catalog))->run();
        self::assertSame('rate_limited',$result->stoppedReason);self::assertSame(1,$result->moviesRequested);self::assertSame(0,$result->filled);
    }

    public function testNotFoundIsSkippedAndExistingDurationsRemainFrozen(): void
    {
        $store = new DurationMemoryStore();
        $catalog = new DurationCatalog();
        $catalog->failure = new TmdbException('not_found', 404);

        $result = (new WatchHistoryDurationBackfill($store, $catalog))->run();

        self::assertNull($result->stoppedReason);
        self::assertSame(0, $result->filled);
        self::assertSame(6, $result->remaining);
        self::assertSame(99, $store->rows[6]['duration']);
        self::assertSame([['movie',603],['season',1396,1],['season',1396,2]], $catalog->calls);
    }
}

final class DurationMemoryStore implements WatchHistoryDurationStore
{
    public array $rows=[
        ['type'=>'movie','source'=>'tmdb','id'=>603,'season'=>0,'episode'=>0,'duration'=>null],
        ['type'=>'movie','source'=>'tmdb','id'=>603,'season'=>0,'episode'=>0,'duration'=>null],
        ['type'=>'episode','source'=>'tmdb','id'=>1396,'season'=>1,'episode'=>1,'duration'=>null],
        ['type'=>'episode','source'=>'tmdb','id'=>1396,'season'=>1,'episode'=>2,'duration'=>null],
        ['type'=>'episode','source'=>'tmdb','id'=>1396,'season'=>2,'episode'=>1,'duration'=>null],
        ['type'=>'episode','source'=>'other','id'=>1,'season'=>1,'episode'=>1,'duration'=>null],
        ['type'=>'movie','source'=>'tmdb','id'=>550,'season'=>0,'episode'=>0,'duration'=>99],
    ];
    public function countMissingDuration():int{return count(array_filter($this->rows,fn($r)=>$r['duration']===null));}
    public function missingMovieIdentities():array{$out=[];foreach($this->rows as$r)if($r['type']==='movie'&&$r['duration']===null)$out[$r['source'].':'.$r['id']]=['source'=>$r['source'],'source_id'=>$r['id']];return array_values($out);}
    public function missingEpisodeSeasons():array{$out=[];foreach($this->rows as$r)if($r['type']==='episode'&&$r['duration']===null)$out[$r['source'].':'.$r['id'].':'.$r['season']]=['source'=>$r['source'],'source_id'=>$r['id'],'season_number'=>$r['season']];return array_values($out);}
    public function fillMovieDuration(string$s,int$i,int$d):int{return$this->fill(fn($r)=>$r['type']==='movie'&&$r['source']===$s&&$r['id']===$i,$d);}
    public function fillEpisodeDuration(string$s,int$i,int$n,int$e,int$d):int{return$this->fill(fn($r)=>$r['type']==='episode'&&$r['source']===$s&&$r['id']===$i&&$r['season']===$n&&$r['episode']===$e,$d);}
    private function fill(callable$match,int$d):int{$count=0;foreach($this->rows as&$row)if($row['duration']===null&&$match($row)){$row['duration']=$d;$count++;}unset($row);return$count;}
}

final class DurationCatalog implements TmdbCatalog
{
    public bool$configured=true;public?TmdbException$failure=null;public array$calls=[];
    public function configured():bool{return$this->configured;}
    public function movieDetails(int$id):TmdbMediaDetails{$this->calls[]=['movie',$id];if($this->failure)throw$this->failure;return new TmdbMediaDetails('tmdb','movie',$id,'Matrix',null,null,null,null,null,null,null,[],null,null,null,'en',false,null,136,null,null,null,null);}
    public function seasonDetails(int$id,int$season):TmdbSeasonDetails{$this->calls[]=['season',$id,$season];if($this->failure)throw$this->failure;$episodes=$season===1?[new TmdbEpisode(1,1,1,'One',null,null,47,null),new TmdbEpisode(2,1,2,'Two',null,null,null,null)]:[new TmdbEpisode(3,2,1,'Three',null,null,50,null)];return new TmdbSeasonDetails('tmdb',$id,$season,$season,'Season',null,null,null,$episodes);}
    public function seriesDetails(int$id):TmdbMediaDetails{throw new \LogicException();}public function configuration():TmdbImageConfiguration{throw new \LogicException();}public function searchMovies(string$q,int$p=1):array{return[];}public function searchSeries(string$q,int$p=1):array{return[];}
}
