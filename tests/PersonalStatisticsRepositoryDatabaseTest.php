<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Repositories\PersonalStatisticsRepository;
use App\Repositories\UserRepository;
use PHPUnit\Framework\TestCase;

final class PersonalStatisticsRepositoryDatabaseTest extends TestCase
{
    private Database$database;private PersonalStatisticsRepository$statistics;private UserRepository$users;
    protected function setUp():void
    {
        $name=getenv('FLICKARY_TEST_DB_DATABASE');if(!is_string($name)||!str_ends_with($name,'_test'))self::markTestSkipped('Disposable Flickary database was not configured.');
        $this->database=new Database(['host'=>getenv('FLICKARY_TEST_DB_HOST')?:'127.0.0.1','port'=>(int)(getenv('FLICKARY_TEST_DB_PORT')?:3306),'database'=>$name,'username'=>getenv('FLICKARY_TEST_DB_USERNAME')?:'','password'=>getenv('FLICKARY_TEST_DB_PASSWORD')?:'','charset'=>'utf8mb4']);
        $this->statistics=new PersonalStatisticsRepository($this->database);$this->users=new UserRepository($this->database);$this->clear();
    }
    protected function tearDown():void{if(isset($this->database))$this->clear();}

    public function testZeroDataReturnsTwelveEmptyMonths():void
    {
        $user=$this->users->create('stats.zero',null);$stats=$this->statistics->readForUser($user,'2026-09-21');
        self::assertFalse($stats->hasAnyData());self::assertSame(0,$stats->totalViews);self::assertCount(12,$stats->monthly);self::assertSame(0,array_sum(array_map(fn($m)=>$m->count,$stats->monthly)));
    }

    public function testAllAggregatesAndUserIsolation():void
    {
        $user=$this->users->create('stats.owner',null);$other=$this->users->create('stats.other',null);
        $events=[
            ['movie',603,null,null,'2026-09-21',136],['movie',603,null,null,'2026-09-20',136],['movie',603,null,null,'2026-08-15',null],
            ['episode',1396,1,1,'2026-09-21',47],['episode',1396,1,1,'2026-09-20',47],['episode',1396,1,2,'2025-10-01',45],
        ];
        foreach($events as$i=>$event)$this->history($user,$event,str_pad(dechex($i+1),32,'0',STR_PAD_LEFT));
        $this->history($other,['movie',550,null,null,'2026-09-21',1000],str_repeat('f',32));
        foreach(['planned','planned','watching','completed']as$i=>$status)$this->media($user,700+$i,$status);
        $this->media($other,999,'dropped');$this->progress($user,1);$this->progress($user,2);$this->progress($other,1);
        $this->schedule($user,801,'2026-09-21');$this->schedule($user,802,'2026-09-22');$this->schedule($user,803,'2026-09-20');$this->schedule($other,900,'2026-09-21');
        $stats=$this->statistics->readForUser($user,'2026-09-21');
        self::assertSame(6,$stats->totalViews);self::assertSame(3,$stats->movieViews);self::assertSame(3,$stats->episodeViews);
        self::assertSame(1,$stats->uniqueMovies);self::assertSame(1,$stats->uniqueSeries);self::assertSame(4,$stats->activeDays);self::assertSame(3,$stats->rewatches);
        self::assertSame(411,$stats->knownMinutes);self::assertSame(1,$stats->unknownDurationEvents);self::assertSame(4,$stats->currentMonthViews);self::assertSame(5,$stats->currentYearViews);
        $months=array_column(array_map(fn($m)=>['key'=>$m->month,'count'=>$m->count],$stats->monthly),'count','key');self::assertSame(1,$months['2025-10']);self::assertSame(1,$months['2026-08']);self::assertSame(4,$months['2026-09']);
        self::assertSame(['planned'=>2,'watching'=>1,'paused'=>0,'completed'=>1,'dropped'=>0],$stats->libraryByStatus);self::assertSame(4,$stats->libraryTotal);self::assertSame(2,$stats->watchedEpisodeProgress);
        self::assertSame(3,$stats->scheduleTotal);self::assertSame(1,$stats->scheduleToday);self::assertSame(1,$stats->scheduleFuture);self::assertSame(1,$stats->scheduleOverdue);
    }

    private function history(int$user,array$e,string$key):void
    {
        [$type,$sourceId,$season,$episode,$date,$duration]=$e;$s=$this->database->connection()->prepare('INSERT INTO watch_history(user_id,source,entry_type,source_id,season_number,episode_number,title,episode_title,watched_on,request_key,duration_minutes) VALUES(:u,\'tmdb\',:t,:sid,:sn,:en,:title,:episode_title,:watched,:request_key,:duration)');
        $s->execute(['u'=>$user,'t'=>$type,'sid'=>$sourceId,'sn'=>$season,'en'=>$episode,'title'=>$type==='movie'?'Matrix':'Breaking Bad','episode_title'=>$type==='episode'?'Episode':null,'watched'=>$date,'request_key'=>$key,'duration'=>$duration]);
    }
    private function media(int$user,int$id,string$status):void{$s=$this->database->connection()->prepare('INSERT INTO user_media(user_id,source,media_type,source_id,status,title)VALUES(:u,\'tmdb\',\'movie\',:id,:status,\'Title\')');$s->execute(['u'=>$user,'id'=>$id,'status'=>$status]);}
    private function progress(int$user,int$episode):void{$s=$this->database->connection()->prepare('INSERT INTO user_series_episode_progress(user_id,source,series_source_id,season_number,episode_number)VALUES(:u,\'tmdb\',1396,1,:e)');$s->execute(['u'=>$user,'e'=>$episode]);}
    private function schedule(int$user,int$id,string$date):void{$s=$this->database->connection()->prepare('INSERT INTO user_schedule(user_id,source,entry_type,source_id,title,scheduled_on)VALUES(:u,\'tmdb\',\'movie\',:id,\'Title\',:d)');$s->execute(['u'=>$user,'id'=>$id,'d'=>$date]);}
    private function clear():void{$pdo=$this->database->connection();$pdo->exec('DELETE FROM user_schedule');$pdo->exec('DELETE FROM watch_history');$pdo->exec('DELETE FROM user_series_episode_progress');$pdo->exec('DELETE FROM user_media');$pdo->exec('DELETE FROM user_external_identities');$pdo->exec('DELETE FROM user_profiles');$pdo->exec('DELETE FROM users');}
}
