<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Repositories\UserScheduleRepository;
use App\Repositories\UserRepository;
use App\Schedule\ScheduleItem;
use PHPUnit\Framework\TestCase;

final class UserScheduleRepositoryDatabaseTest extends TestCase
{
    private Database $database; private UserScheduleRepository $schedule; private UserRepository $users;
    protected function setUp():void
    {
        $name=getenv('FLICKARY_TEST_DB_DATABASE'); if(!is_string($name)||!str_ends_with($name,'_test'))self::markTestSkipped('Disposable Flickary database was not configured.');
        $this->database=new Database(['host'=>getenv('FLICKARY_TEST_DB_HOST')?:'127.0.0.1','port'=>(int)(getenv('FLICKARY_TEST_DB_PORT')?:3306),'database'=>$name,'username'=>getenv('FLICKARY_TEST_DB_USERNAME')?:'','password'=>getenv('FLICKARY_TEST_DB_PASSWORD')?:'','charset'=>'utf8mb4']);
        $this->schedule=new UserScheduleRepository($this->database);$this->users=new UserRepository($this->database);$this->clear();
    }
    protected function tearDown():void{if(isset($this->database))$this->clear();}
    public function testSchemaUniqueIndexesCascadeAndUserScope():void
    {
        $pdo=$this->database->connection();$names=array_unique(array_column($pdo->query('SHOW INDEX FROM user_schedule')->fetchAll(),'Key_name'));
        self::assertContains('uq_user_schedule_identity',$names);self::assertContains('idx_user_schedule_chronology',$names);self::assertContains('idx_user_schedule_type',$names);
        self::assertStringContainsString('ON DELETE CASCADE',(string)$pdo->query('SHOW CREATE TABLE user_schedule')->fetchColumn(1));
        $one=$this->users->create('schedule.one',null);$two=$this->users->create('schedule.two',null);$item=$this->movie('2030-01-02');
        self::assertTrue($this->schedule->create($one,$item));self::assertFalse($this->schedule->create($one,$this->movie('2030-01-03')));self::assertSame(1,$this->schedule->countForUser($one));self::assertSame('2030-01-03',$this->schedule->findForUserIdentity($one,'tmdb','movie',603)?->scheduledOn);self::assertTrue($this->schedule->create($two,$item));
        $series=new ScheduleItem('tmdb','series',1396,0,0,'Breaking Bad',null,null,'2008-01-20',null,'2030-01-02');
        self::assertTrue($this->schedule->create($one,$series));self::assertFalse($this->schedule->create($one,$series));
        $episode=new ScheduleItem('tmdb','episode',1396,0,1,'Breaking Bad',null,'Piloto','2008-01-20',null,'2030-01-02');
        self::assertTrue($this->schedule->create($one,$episode));self::assertFalse($this->schedule->create($one,$episode));
        self::assertTrue($this->schedule->create($one,new ScheduleItem('tmdb','episode',1396,0,2,'Breaking Bad',null,'Segundo',null,null,'2030-01-02')));
        $pdo->prepare('DELETE FROM users WHERE id=:id')->execute(['id'=>$one]);self::assertSame(0,$this->schedule->countForUser($one));
    }
    public function testTypesOrderingFilteringEpisodeMapOwnershipUpdateAndDelete():void
    {
        $owner=$this->users->create('schedule.owner',null);$other=$this->users->create('schedule.other',null);
        $this->schedule->create($owner,$this->movie('2030-01-03'));$this->schedule->create($owner,new ScheduleItem('tmdb','series',1396,0,0,'Breaking Bad',null,null,'2008-01-20',null,'2030-01-02'));$this->schedule->create($owner,new ScheduleItem('tmdb','episode',1396,1,1,'Breaking Bad',null,'Piloto','2008-01-20',null,'2030-01-01'));
        $all=$this->schedule->paginateForUser($owner,null,'2030-01-02',1,2);self::assertSame(3,$all->total);self::assertSame('series',$all->items[0]->entryType);self::assertSame(2,$all->lastPage());self::assertCount(1,$this->schedule->paginateForUser($owner,'movie','2030-01-02',1,30)->items);
        $map=$this->schedule->episodeSchedulesForSeason($owner,'tmdb',1396,1);self::assertArrayHasKey(1,$map);$id=$map[1]->id;self::assertNull($this->schedule->findForUser($other,$id));self::assertFalse($this->schedule->updateDate($other,$id,'2030-02-01'));self::assertTrue($this->schedule->updateDate($owner,$id,'2030-02-01'));self::assertFalse($this->schedule->delete($other,$id));self::assertTrue($this->schedule->delete($owner,$id));
    }
    private function movie(string$date):ScheduleItem{return new ScheduleItem('tmdb','movie',603,0,0,'Matrix','The Matrix',null,'1999-03-31',null,$date);}
    private function clear():void{$pdo=$this->database->connection();$pdo->exec('DELETE FROM user_schedule');$pdo->exec('DELETE FROM watch_history');$pdo->exec('DELETE FROM user_series_episode_progress');$pdo->exec('DELETE FROM user_media');$pdo->exec('DELETE FROM user_external_identities');$pdo->exec('DELETE FROM user_profiles');$pdo->exec('DELETE FROM users');}
}
