<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Messaging\DirectMessageBody;
use App\Repositories\DirectMessageRepository;
use App\Repositories\UserFollowRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSocialControlRepository;
use App\Repositories\PublicProfileRepository;
use App\Repositories\SocialFeedRepository;
use App\Repositories\SocialActivityCommentRepository;
use App\Repositories\SocialActivityLikeRepository;
use App\Social\SocialActivityCommentCreateResult;
use App\Social\SocialActivityLikeResult;
use App\Social\SocialCommentBody;
use PHPUnit\Framework\TestCase;

final class UserSocialControlRepositoryDatabaseTest extends TestCase
{
    private Database $database;private UserRepository $users;private UserProfileRepository $profiles;private UserFollowRepository $follows;private DirectMessageRepository $messages;private UserSocialControlRepository $controls;
    protected function setUp():void{$name=getenv('FLICKARY_TEST_DB_DATABASE');if(!is_string($name)||!str_ends_with($name,'_test')){self::markTestSkipped('Disposable Flickary database was not configured.');}$this->database=new Database(['host'=>getenv('FLICKARY_TEST_DB_HOST')?:'127.0.0.1','port'=>(int)(getenv('FLICKARY_TEST_DB_PORT')?:3306),'database'=>$name,'username'=>getenv('FLICKARY_TEST_DB_USERNAME')?:'','password'=>getenv('FLICKARY_TEST_DB_PASSWORD')?:'','charset'=>'utf8mb4']);$this->users=new UserRepository($this->database);$this->profiles=new UserProfileRepository($this->database);$this->follows=new UserFollowRepository($this->database);$this->messages=new DirectMessageRepository($this->database);$this->controls=new UserSocialControlRepository($this->database);$this->clear();}
    protected function tearDown():void{if(isset($this->database)){$this->clear();}}

    public function testBlockCleansRelationshipButPreservesHistoryAndUnblockRestoresNothing():void
    {
        $a=$this->user('control.a');$b=$this->user('control.b');$this->follows->follow($a,$b);$this->follows->follow($b,$a);[$body]=DirectMessageBody::validate('before block');self::assertTrue($this->messages->send($a,$b,$body));self::assertTrue($this->messages->send($b,$a,$body));$conversation=$this->messages->conversationForUsers($a,$b);self::assertNotNull($conversation);
        $pdo=$this->database->connection();$pdo->prepare('INSERT INTO user_follow_requests(requester_user_id,target_user_id) VALUES (?,?),(?,?)')->execute([$a,$b,$b,$a]);$pdo->prepare('INSERT INTO user_mutes(user_id,muted_user_id) VALUES (?,?),(?,?)')->execute([$a,$b,$b,$a]);$pdo->prepare("INSERT INTO social_notifications(recipient_user_id,actor_user_id,type) VALUES (?,?,'new_follower'),(?,?,'new_follower')")->execute([$a,$b,$b,$a]);
        $pdo->prepare("INSERT INTO watch_history(user_id,source,entry_type,source_id,title,watched_on,request_key) VALUES (?,'tmdb','movie',1,'Film',CURRENT_DATE,?)")->execute([$a,str_repeat('a',32)]);$history=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO social_activity_likes(history_id,user_id) VALUES (?,?)')->execute([$history,$b]);$pdo->prepare("INSERT INTO social_activity_comments(history_id,user_id,body) VALUES (?,?,'historic')")->execute([$history,$b]);
        $this->controls->block($a,$b);$this->controls->block($a,$b);self::assertTrue($this->controls->isBlockedBy($a,$b));self::assertTrue($this->controls->isBlockedEitherDirection($b,$a));self::assertSame(1,$this->rowCount('user_blocks'));foreach(['user_follows','user_follow_requests','user_mutes','social_notifications']as$table){self::assertSame(0,$this->rowCount($table));}self::assertSame(2,$this->rowCount('direct_messages'));self::assertSame(1,$this->rowCount('social_activity_likes'));self::assertSame(1,$this->rowCount('social_activity_comments'));
        self::assertNotNull($pdo->query('SELECT read_at FROM direct_messages WHERE sender_user_id='.$b)->fetchColumn());self::assertNull($pdo->query('SELECT read_at FROM direct_messages WHERE sender_user_id='.$a)->fetchColumn());self::assertFalse($this->messages->send($a,$b,$body));$inbox=$this->messages->paginateConversations($a,1,30);self::assertTrue($inbox->items[0]->pairBlocked);self::assertFalse($inbox->items[0]->canSendNow);
        $this->controls->unblock($a,$b);$this->controls->unblock($a,$b);self::assertFalse($this->controls->isBlockedEitherDirection($a,$b));self::assertSame(0,$this->rowCount('user_follows'));self::assertFalse($this->messages->send($a,$b,$body));$this->follows->follow($a,$b);$this->follows->follow($b,$a);self::assertTrue($this->messages->send($a,$b,$body));self::assertSame($conversation->id,$this->messages->conversationForUsers($a,$b)?->id);
    }

    public function testMuteRequiresOutgoingFollowAndIsIdempotent():void
    {
        $a=$this->user('mute.a');$b=$this->user('mute.b');self::assertFalse($this->controls->mute($a,$b));self::assertFalse($this->controls->isMuted($a,$b));$this->follows->follow($a,$b);self::assertTrue($this->controls->mute($a,$b));self::assertTrue($this->controls->mute($a,$b));self::assertSame(1,$this->controls->paginateMuted($a,1,30)->total);$this->follows->unfollow($a,$b);self::assertFalse($this->controls->isMuted($a,$b));$this->controls->unmute($a,$b);self::assertSame(0,$this->rowCount('user_mutes'));
    }

    public function testSelfControlsAreRejected():void{$a=$this->user('control.self');$this->expectException(\InvalidArgumentException::class);$this->controls->block($a,$a);}

    public function testBlockAndMuteAreAppliedToSearchListsFeedAndActivityInteractions():void
    {
        $a=$this->user('guard.viewer');$b=$this->user('guard.blocked');$c=$this->user('guard.owner');$pdo=$this->database->connection();
        $this->follows->follow($a,$b);$this->follows->follow($a,$c);$this->follows->follow($b,$c);$pdo->prepare('UPDATE user_profiles SET share_watch_activity=1,share_watch_activity_since=DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 1 DAY) WHERE user_id IN (?,?)')->execute([$b,$c]);
        $historyB=$this->history($b,'b');$historyC=$this->history($c,'c');$pdo->prepare("INSERT INTO social_activity_comments(history_id,user_id,body) VALUES (?,?,'blocked comment'),(?,?,'viewer comment')")->execute([$historyC,$b,$historyC,$a]);
        $this->controls->mute($a,$b);$feed=(new SocialFeedRepository($this->database))->paginateForViewer($a,1,30);self::assertSame([$historyC],array_map(static fn($item)=>$item->historyId,$feed->items));$this->controls->unmute($a,$b);
        $this->controls->block($a,$b);$profiles=new PublicProfileRepository($this->database);self::assertSame(0,$profiles->searchForViewer('guard.blocked',1,20,$a)->total);self::assertSame(1,$profiles->search('guard.blocked',1,20)->total);
        self::assertSame(['guard.viewer'],array_map(static fn($profile)=>$profile->username,$this->follows->paginateFollowersForViewer($c,1,30,$a)->items));
        $comments=new SocialActivityCommentRepository($this->database);self::assertNotNull($comments->detailForViewer($a,$historyC));self::assertSame(1,$comments->paginateVisibleForActivity($historyC,1,30,$a,$c)->total);self::assertSame(2,$comments->paginateVisibleForActivity($historyC,1,30,$c,$c)->total);self::assertNull($comments->detailForViewer($a,$historyB));
        [$body]=SocialCommentBody::validate('new comment');self::assertSame(SocialActivityCommentCreateResult::Hidden,$comments->createAuthorized($a,$historyB,$body));self::assertSame(SocialActivityLikeResult::Hidden,(new SocialActivityLikeRepository($this->database))->likeVisibleActivity($a,$historyB));
    }
    private function user(string$username):int{$id=$this->users->create($username,null);$this->profiles->create($id,$username,null);return$id;}
    private function history(int$userId,string$key):int{$statement=$this->database->connection()->prepare("INSERT INTO watch_history(user_id,source,entry_type,source_id,title,watched_on,request_key) VALUES (?,'tmdb','movie',?,'Film',CURRENT_DATE,?)");$statement->execute([$userId,ord($key),str_repeat($key,32)]);return(int)$this->database->connection()->lastInsertId();}
    private function rowCount(string$table):int{if(!in_array($table,['user_blocks','user_mutes','user_follows','user_follow_requests','social_notifications','direct_messages','social_activity_likes','social_activity_comments'],true)){throw new \InvalidArgumentException();}return(int)$this->database->connection()->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();}
    private function clear():void{$this->database->connection()->exec('DELETE FROM users');}
}
