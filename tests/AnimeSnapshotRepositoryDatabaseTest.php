<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Repositories\AnimeSnapshotRepository;
use App\Repositories\UserRepository;
use PHPUnit\Framework\TestCase;

final class AnimeSnapshotRepositoryDatabaseTest extends TestCase
{
    private Database $database;private AnimeSnapshotRepository $snapshots;private int$user;
    protected function setUp():void
    {
        $name=getenv('FLICKARY_TEST_DB_DATABASE');if(!is_string($name)||!str_ends_with($name,'_test'))self::markTestSkipped('Disposable Flickary database was not configured.');
        $this->database=new Database(['host'=>getenv('FLICKARY_TEST_DB_HOST')?:'127.0.0.1','port'=>(int)(getenv('FLICKARY_TEST_DB_PORT')?:3306),'database'=>$name,'username'=>getenv('FLICKARY_TEST_DB_USERNAME')?:'','password'=>getenv('FLICKARY_TEST_DB_PASSWORD')?:'','charset'=>'utf8mb4']);$this->clear();$this->user=(new UserRepository($this->database))->create('anime.repo',null);$this->snapshots=new AnimeSnapshotRepository($this->database);
    }
    protected function tearDown():void{if(isset($this->database))$this->clear();}
    public function testGlobalIdentitiesFillOnlyNullTmdbSnapshotsWithoutChangingOtherFields():void
    {
        $pdo=$this->database->connection();
        $pdo->exec("INSERT INTO user_media(user_id,source,media_type,source_id,status,title,is_anime)VALUES({$this->user},'tmdb','movie',1,'watching','Keep movie',NULL),({$this->user},'other','movie',1,'planned','Other',NULL),({$this->user},'tmdb','movie',9,'completed','Already',0),({$this->user},'tmdb','series',2,'watching','Keep series',NULL)");
        $pdo->exec("INSERT INTO watch_history(user_id,source,entry_type,source_id,season_number,episode_number,title,episode_title,watched_on,request_key,is_anime)VALUES({$this->user},'tmdb','movie',1,NULL,NULL,'Keep history',NULL,'2026-09-22','00000000000000000000000000000001',NULL),({$this->user},'tmdb','episode',2,1,1,'Keep episode','Pilot','2026-09-22','00000000000000000000000000000002',NULL)");
        $pdo->exec("INSERT INTO user_schedule(user_id,source,entry_type,source_id,season_number,episode_number,title,episode_title,scheduled_on,is_anime)VALUES({$this->user},'tmdb','movie',1,0,0,'Keep schedule',NULL,'2026-10-01',NULL),({$this->user},'tmdb','episode',2,1,1,'Keep series schedule','Pilot','2026-10-01',NULL)");
        self::assertSame([1],$this->snapshots->pendingMovieIds());self::assertSame([2],$this->snapshots->pendingSeriesIds());self::assertSame(6,$this->snapshots->countPendingTmdb());
        self::assertSame(3,$this->snapshots->fillMovie(1,true));self::assertSame(3,$this->snapshots->fillSeries(2,false));self::assertSame(0,$this->snapshots->countPendingTmdb());
        self::assertSame('Keep movie',$pdo->query("SELECT title FROM user_media WHERE source='tmdb' AND source_id=1")->fetchColumn());
        self::assertSame(1,(int)$pdo->query("SELECT is_anime FROM watch_history WHERE entry_type='movie'")->fetchColumn());
        self::assertSame(0,(int)$pdo->query("SELECT is_anime FROM user_schedule WHERE entry_type='episode'")->fetchColumn());
        self::assertNull($pdo->query("SELECT is_anime FROM user_media WHERE source='other'")->fetchColumn(),'Non-TMDB remains NULL.');
        self::assertSame(0,(int)$pdo->query("SELECT is_anime FROM user_media WHERE source_id=9")->fetchColumn(),'Existing classification is unchanged.');
    }
    private function clear():void{$pdo=$this->database->connection();$pdo->exec('DELETE FROM user_schedule');$pdo->exec('DELETE FROM watch_history');$pdo->exec('DELETE FROM user_series_episode_progress');$pdo->exec('DELETE FROM user_media');$pdo->exec('DELETE FROM user_external_identities');$pdo->exec('DELETE FROM user_profiles');$pdo->exec('DELETE FROM users');}
}
