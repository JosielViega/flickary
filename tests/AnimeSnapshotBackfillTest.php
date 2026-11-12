<?php

declare(strict_types=1);

namespace Tests;

use App\Anime\AnimeSnapshotBackfill;
use App\Anime\AnimeSnapshotBackfillStore;
use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageConfiguration;
use App\Integrations\Tmdb\TmdbMediaDetails;
use App\Integrations\Tmdb\TmdbSeasonDetails;
use PHPUnit\Framework\TestCase;

final class AnimeSnapshotBackfillTest extends TestCase
{
    public function testGloballyDeduplicatedIdentitiesFillTrueAndFalseAndSecondRunIsIdempotent(): void
    {
        $store = new MemoryAnimeSnapshotStore([1311031,603], [65733,1396], 9);
        $catalog = new AnimeBackfillCatalog();
        $first = (new AnimeSnapshotBackfill($store, $catalog))->run();

        self::assertSame([['movie',603],['movie',1311031],['series',1396],['series',65733]], $catalog->calls);
        self::assertSame(2, $first->moviesRequested);
        self::assertSame(2, $first->seriesRequested);
        self::assertSame(5, $first->classifiedAnime);
        self::assertSame(4, $first->classifiedNonAnime);
        self::assertSame(0, $first->remaining);

        $catalog->calls = [];
        $second = (new AnimeSnapshotBackfill($store, $catalog))->run();
        self::assertSame(0, $second->initiallyPending);
        self::assertSame([], $catalog->calls);
    }

    public function testNotFoundStaysPendingWhileAuthorizationFailureStopsSafely(): void
    {
        $store = new MemoryAnimeSnapshotStore([404,500], [], 2);
        $catalog = new AnimeBackfillCatalog();
        $catalog->failures = [404=>'not_found',500=>'unauthorized'];
        $result = (new AnimeSnapshotBackfill($store, $catalog))->run();
        self::assertSame('unauthorized', $result->stoppedReason);
        self::assertSame(2, $result->remaining);
        self::assertSame([['movie',404],['movie',500]], $catalog->calls, 'Not found continues; a later fatal credential error stops the run.');
    }

    public function testMissingConfigurationChangesNothing(): void
    {
        $store = new MemoryAnimeSnapshotStore([1], [2], 3);
        $catalog = new AnimeBackfillCatalog(false);
        $result = (new AnimeSnapshotBackfill($store, $catalog))->run();
        self::assertSame('not_configured', $result->stoppedReason);
        self::assertSame(3, $result->remaining);
        self::assertSame([], $catalog->calls);
    }
}

final class MemoryAnimeSnapshotStore implements AnimeSnapshotBackfillStore
{
    /** @param list<int> $movies @param list<int> $series */
    public function __construct(public array $movies, public array $series, public int $pending) { sort($this->movies); sort($this->series); }
    public function countPendingTmdb():int{return$this->pending;}
    public function pendingMovieIds():array{return$this->movies;}
    public function pendingSeriesIds():array{return$this->series;}
    public function fillMovie(int$id,bool$anime):int{return$this->fill($id,$anime,'movie');}
    public function fillSeries(int$id,bool$anime):int{return$this->fill($id,$anime,'series');}
    private function fill(int$id,bool$anime,string$type):int{$count=$anime?($type==='movie'?2:3):($type==='movie'?2:2);$this->pending-=$count;$this->{$type==='movie'?'movies':'series'}=array_values(array_filter($this->{$type==='movie'?'movies':'series'},fn($value)=>$value!==$id));return$count;}
}

final class AnimeBackfillCatalog implements TmdbCatalog
{
    public array$calls=[];public array$failures=[];public function __construct(private bool$ready=true){}
    public function configured():bool{return$this->ready;}
    public function movieDetails(int$id):TmdbMediaDetails{$this->calls[]=['movie',$id];return$this->details($id,'movie');}
    public function seriesDetails(int$id):TmdbMediaDetails{$this->calls[]=['series',$id];return$this->details($id,'series');}
    private function details(int$id,string$type):TmdbMediaDetails{if(isset($this->failures[$id]))throw new TmdbException($this->failures[$id]);$anime=in_array($id,[1311031,65733],true);return new TmdbMediaDetails('tmdb',$type,$id,'Title',null,null,null,null,null,null,null,$anime?[['id'=>16,'name'=>'Animation']]:[['id'=>18,'name'=>'Drama']],null,null,null,$anime?'ja':'en',false,null,null,null,null,null,null);}
    public function seasonDetails(int$i,int$s):TmdbSeasonDetails{throw new \LogicException();}
    public function configuration():TmdbImageConfiguration{throw new \LogicException();}
    public function searchMovies(string$q,int$p=1):array{return[];}public function searchSeries(string$q,int$p=1):array{return[];}public function discoverAnimeMovies(int$p=1):array{return[];}public function discoverAnimeSeries(int$p=1):array{return[];}
}
