<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Repositories\PublicProfileRepository;
use App\Repositories\UserFollowRepository;
use App\Repositories\UserFollowRequestRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Profiles\ProfileUpdateService;
use PHPUnit\Framework\TestCase;

final class SocialRepositoryDatabaseTest extends TestCase
{
    private Database $database;
    private UserRepository $users;
    private UserProfileRepository $profiles;
    private PublicProfileRepository $publicProfiles;
    private UserFollowRepository $follows;
    private UserFollowRequestRepository $followRequests;

    protected function setUp(): void
    {
        $name=getenv('FLICKARY_TEST_DB_DATABASE');
        if(!is_string($name)||!str_ends_with($name,'_test')){self::markTestSkipped('Disposable Flickary database was not configured.');}
        $this->database=new Database(['host'=>getenv('FLICKARY_TEST_DB_HOST')?:'127.0.0.1','port'=>(int)(getenv('FLICKARY_TEST_DB_PORT')?:3306),'database'=>$name,'username'=>getenv('FLICKARY_TEST_DB_USERNAME')?:'','password'=>getenv('FLICKARY_TEST_DB_PASSWORD')?:'','charset'=>'utf8mb4']);
        $this->users=new UserRepository($this->database); $this->profiles=new UserProfileRepository($this->database);
        $this->publicProfiles=new PublicProfileRepository($this->database); $this->follows=new UserFollowRepository($this->database);
        $this->followRequests=new UserFollowRequestRepository($this->database);
        $this->clear();
    }

    protected function tearDown():void{if(isset($this->database)){$this->clear();}}

    public function testFollowLifecycleCountsPagesAndCascadeBothDirections():void
    {
        $a=$this->create('social.a','Social A'); $b=$this->create('social.b','Social B'); $c=$this->create('social.c','Social C');
        $this->follows->follow($a,$b); $this->follows->follow($a,$b); $this->follows->follow($c,$b); $this->follows->follow($a,$c);
        $duplicateBlocked=false;
        try{$insert=$this->database->connection()->prepare('INSERT INTO user_follows(follower_user_id,followed_user_id) VALUES (?,?)');$insert->execute([$a,$b]);}catch(\PDOException){$duplicateBlocked=true;}
        self::assertTrue($duplicateBlocked);
        self::assertTrue($this->follows->isFollowing($a,$b)); self::assertSame(2,$this->follows->countFollowers($b)); self::assertSame(2,$this->follows->countFollowing($a));
        self::assertSame(['social.c','social.a'],array_map(static fn($p)=>$p->username,$this->follows->paginateFollowers($b,1,30)->items));
        self::assertCount(2,$this->follows->paginateFollowing($a,1,30)->items);
        $this->follows->unfollow($a,$b); $this->follows->unfollow($a,$b); self::assertFalse($this->follows->isFollowing($a,$b));
        $this->database->connection()->exec('DELETE FROM users WHERE id='.(int)$c);
        self::assertSame(0,$this->follows->countFollowers($b)); self::assertSame(0,$this->follows->countFollowing($a));
    }

    public function testPublicReaderNeverReturnsPrivateAccountFieldsAndRanksSearch():void
    {
        $a=$this->create('matrix','Zulu'); $b=$this->create('matrix.fan','Matrix Fan'); $this->create('another.user','The Matrix Club',true);
        $profile=$this->publicProfiles->findByUsername('matrix');
        self::assertNotNull($profile); self::assertSame($a,$profile->userId); self::assertFalse(property_exists($profile,'email'));
        $results=$this->publicProfiles->search('matrix',1,20);
        self::assertSame(['matrix','matrix.fan','another.user'],array_map(static fn($p)=>$p->username,$results->items));
        self::assertTrue($results->items[2]->isPrivate);
        self::assertSame(0,$this->publicProfiles->search('%_',1,20)->total);
        self::assertNull($this->publicProfiles->findByUsername('missing.user'));
    }

    public function testFollowRequestLifecycleIsIdempotentPaginatedAndAcceptedAtomically():void
    {
        $requester=$this->create('requester.user','Requester'); $target=$this->create('target.user','Target',true);
        $this->followRequests->request($requester,$target); $this->followRequests->request($requester,$target);
        self::assertTrue($this->followRequests->isPending($requester,$target));
        self::assertSame(1,$this->followRequests->countIncoming($target));
        $page=$this->followRequests->paginateIncoming($target,1,30);
        self::assertSame('requester.user',$page->items[0]->requester->username);
        self::assertTrue($this->followRequests->accept($requester,$target));
        self::assertFalse($this->followRequests->accept($requester,$target));
        self::assertTrue($this->follows->isFollowing($requester,$target));
        self::assertFalse($this->followRequests->isPending($requester,$target));
        $this->follows->unfollow($requester,$target);
        $this->followRequests->request($requester,$target);
        self::assertTrue($this->followRequests->reject($requester,$target));
        self::assertFalse($this->followRequests->reject($requester,$target));
        self::assertFalse($this->follows->isFollowing($requester,$target));
        $this->followRequests->cancel($requester,$target);
    }

    public function testSelfRequestAndAcceptedRelationshipCannotCreatePendingRow():void
    {
        $a=$this->create('guard.user','Guard'); $b=$this->create('accepted.user','Accepted');
        $this->followRequests->request($a,$a);
        $this->follows->follow($a,$b); $this->followRequests->request($a,$b);
        self::assertFalse($this->followRequests->isPending($a,$a));
        self::assertFalse($this->followRequests->isPending($a,$b));
    }

    public function testFollowRequestsCascadeWhenRequesterOrTargetIsDeleted():void
    {
        $requester=$this->create('cascade.requester','Requester'); $target=$this->create('cascade.target','Target',true);
        $this->followRequests->request($requester,$target);
        $this->database->connection()->exec('DELETE FROM users WHERE id='.(int)$requester);
        self::assertSame(0,$this->followRequests->countIncoming($target));
        $requester=$this->create('cascade.requester2','Requester 2');
        $this->followRequests->request($requester,$target);
        $this->database->connection()->exec('DELETE FROM users WHERE id='.(int)$target);
        self::assertFalse($this->followRequests->isPending($requester,$target));
    }

    public function testPublishingPrivateProfileAcceptsAllPendingRequestsTransactionally():void
    {
        $target=$this->create('publish.user','Publish',true);
        $a=$this->create('publish.a','A'); $b=$this->create('publish.b','B');
        $this->followRequests->request($a,$target); $this->followRequests->request($b,$target);
        (new ProfileUpdateService($this->database,$this->profiles,$this->followRequests))
            ->update($target,'Publish',null,true,false);
        self::assertSame(0,$this->followRequests->countIncoming($target));
        self::assertTrue($this->follows->isFollowing($a,$target));
        self::assertTrue($this->follows->isFollowing($b,$target));
    }

    public function testMakingPublicProfilePrivatePreservesAcceptedFollowers():void
    {
        $target=$this->create('privacy.target','Target'); $follower=$this->create('privacy.follower','Follower');
        $this->follows->follow($follower,$target);
        (new ProfileUpdateService($this->database,$this->profiles,$this->followRequests))
            ->update($target,'Target',null,false,true);
        self::assertTrue($this->follows->isFollowing($follower,$target));
        self::assertTrue($this->publicProfiles->findByUsername('privacy.target')?->isPrivate);
    }

    public function testSchemaHasCompositePrimaryReverseIndexAndTwoCascadeForeignKeys():void
    {
        $pdo=$this->database->connection(); $schema=$pdo->query('SELECT DATABASE()')->fetchColumn();
        $pk=$pdo->prepare("SELECT GROUP_CONCAT(column_name ORDER BY ordinal_position) FROM information_schema.key_column_usage WHERE table_schema=:schema AND table_name='user_follows' AND constraint_name='PRIMARY'");$pk->execute(['schema'=>$schema]);
        self::assertSame('follower_user_id,followed_user_id',$pk->fetchColumn());
        $index=$pdo->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=:schema AND table_name='user_follows' AND index_name='idx_user_follows_followed'");$index->execute(['schema'=>$schema]);self::assertSame(2,(int)$index->fetchColumn());
        $fks=$pdo->prepare("SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema=:schema AND table_name='user_follows' AND delete_rule='CASCADE'");$fks->execute(['schema'=>$schema]);self::assertSame(2,(int)$fks->fetchColumn());
    }

    public function testFollowRequestSchemaHasCompositePrimaryReverseIndexAndCascadeForeignKeys():void
    {
        $pdo=$this->database->connection(); $schema=$pdo->query('SELECT DATABASE()')->fetchColumn();
        $pk=$pdo->prepare("SELECT GROUP_CONCAT(column_name ORDER BY ordinal_position) FROM information_schema.key_column_usage WHERE table_schema=:schema AND table_name='user_follow_requests' AND constraint_name='PRIMARY'");$pk->execute(['schema'=>$schema]);
        self::assertSame('requester_user_id,target_user_id',$pk->fetchColumn());
        $index=$pdo->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=:schema AND table_name='user_follow_requests' AND index_name='idx_user_follow_requests_target'");$index->execute(['schema'=>$schema]);self::assertSame(2,(int)$index->fetchColumn());
        $fks=$pdo->prepare("SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema=:schema AND table_name='user_follow_requests' AND delete_rule='CASCADE'");$fks->execute(['schema'=>$schema]);self::assertSame(2,(int)$fks->fetchColumn());
    }

    public function testPublicStatisticsAndFavoritesAreLocalLimitedAndIsolated():void
    {
        $a=$this->create('stats.public','Stats Public'); $b=$this->create('stats.other','Stats Other'); $pdo=$this->database->connection();
        $media=$pdo->prepare("INSERT INTO user_media(user_id,source,media_type,source_id,status,title) VALUES (?,'tmdb','movie',?,'completed',?)");
        $media->execute([$a,1,'One']); $media->execute([$a,2,'Two']); $media->execute([$b,3,'Other']);
        $preference=$pdo->prepare("INSERT INTO user_media_preferences(user_id,source,media_type,source_id,title,is_favorite,rating,updated_at) VALUES (?,'tmdb','movie',?,?,1,?,?)");
        for($i=1;$i<=7;$i++){$preference->execute([$a,100+$i,'Favorite '.$i,$i===1?null:$i,'2026-01-'.str_pad((string)$i,2,'0',STR_PAD_LEFT).' 10:00:00']);}
        $preference->execute([$b,999,'Other favorite',10,'2026-02-01 10:00:00']);
        $history=$pdo->prepare("INSERT INTO watch_history(user_id,source,entry_type,source_id,title,duration_minutes,watched_on,request_key) VALUES (?,'tmdb','movie',?,?,?,'2026-01-01',?)");
        $history->execute([$a,1,'Known',120,str_repeat('a',32)]); $history->execute([$a,2,'Unknown',null,str_repeat('b',32)]); $history->execute([$b,3,'Other',500,str_repeat('c',32)]);
        $stats=$this->publicProfiles->statisticsForUser($a);
        self::assertSame(2,$stats->libraryItems); self::assertSame(7,$stats->favoriteItems); self::assertSame(6,$stats->ratedItems);
        self::assertSame(4.5,$stats->averageRating); self::assertSame(2,$stats->historyViews); self::assertSame(120,$stats->knownMinutes); self::assertSame(1,$stats->unknownDurationViews);
        $favorites=$this->publicProfiles->recentFavorites($a,6); self::assertCount(6,$favorites); self::assertSame('Favorite 7',$favorites[0]->title); self::assertSame('Favorite 2',$favorites[5]->title);
    }

    private function create(string $username,string $name,bool $private=false):int
    {
        $id=$this->users->create($username,null);$this->profiles->create($id,$name,null);if($private){$this->profiles->updateProfile($id,$name,null,true);}return $id;
    }
    private function clear():void
    {
        $pdo=$this->database->connection();$pdo->exec('DELETE FROM user_follow_requests');$pdo->exec('DELETE FROM user_follows');$pdo->exec('DELETE FROM user_external_identities');$pdo->exec('DELETE FROM user_profiles');$pdo->exec('DELETE FROM users');
    }
}
