<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Notifications\SocialNotificationType;
use App\Repositories\SocialActivityCommentRepository;
use App\Repositories\SocialActivityLikeRepository;
use App\Repositories\SocialNotificationRepository;
use App\Repositories\UserFollowRepository;
use App\Repositories\UserFollowRequestRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Social\SocialActivityCommentCreateResult;
use App\Social\SocialActivityLikeResult;
use App\Social\SocialCommentBody;
use PHPUnit\Framework\TestCase;

final class SocialNotificationRepositoryDatabaseTest extends TestCase
{
    private Database$database;private UserRepository$users;private UserProfileRepository$profiles;private SocialNotificationRepository$notifications;
    protected function setUp():void
    {
        $name=getenv('FLICKARY_TEST_DB_DATABASE');if(!is_string($name)||!str_ends_with($name,'_test')){self::markTestSkipped('Disposable Flickary database was not configured.');}
        $this->database=new Database(['host'=>getenv('FLICKARY_TEST_DB_HOST')?:'127.0.0.1','port'=>(int)(getenv('FLICKARY_TEST_DB_PORT')?:3306),'database'=>$name,'username'=>getenv('FLICKARY_TEST_DB_USERNAME')?:'','password'=>getenv('FLICKARY_TEST_DB_PASSWORD')?:'','charset'=>'utf8mb4']);
        $this->users=new UserRepository($this->database);$this->profiles=new UserProfileRepository($this->database);$this->notifications=new SocialNotificationRepository($this->database);$this->clear();
    }
    protected function tearDown():void{if(isset($this->database)){$this->clear();}}

    public function testPaginationUnreadOwnershipAndDestinations():void
    {
        $recipient=$this->user('notify.recipient');$actor=$this->user('notify.actor');$other=$this->user('notify.other');
        $this->notifications->followRequest($recipient,$actor);$this->notifications->newFollower($recipient,$other);$this->notifications->followAccepted($recipient,$actor);
        $page=$this->notifications->paginateForUser($recipient,1,2);self::assertSame(3,$page->total);self::assertCount(2,$page->items);self::assertSame(3,$this->notifications->countUnread($recipient));
        $notification=$page->items[0];self::assertSame('/u/'.rawurlencode($notification->actorUsername),$notification->destination());self::assertNull($this->notifications->findForRecipient($other,$notification->id));
        self::assertTrue($this->notifications->markRead($recipient,$notification->id));$read=$this->notifications->findForRecipient($recipient,$notification->id);self::assertNotNull($read?->readAt);$timestamp=$read?->readAt;
        self::assertTrue($this->notifications->markRead($recipient,$notification->id));self::assertSame($timestamp,$this->notifications->findForRecipient($recipient,$notification->id)?->readAt);self::assertSame(2,$this->notifications->countUnread($recipient));
        $this->notifications->markAllRead($recipient);self::assertSame(0,$this->notifications->countUnread($recipient));self::assertSame(0,$this->notifications->countUnread($other));
    }

    public function testFollowRequestCancelRejectAcceptAndPublicRefollowLifecycles():void
    {
        $requester=$this->user('follow.requester');$target=$this->user('follow.target');$follows=new UserFollowRepository($this->database,$this->notifications);$requests=new UserFollowRequestRepository($this->database,$this->notifications);
        $requests->request($requester,$target);$requests->request($requester,$target);self::assertSame(1,$this->countType(SocialNotificationType::FollowRequest));
        $requests->cancel($requester,$target);self::assertSame(0,$this->countType(SocialNotificationType::FollowRequest));
        $requests->request($requester,$target);self::assertTrue($requests->reject($requester,$target));self::assertSame(0,$this->countType(SocialNotificationType::FollowRequest));
        $requests->request($requester,$target);self::assertTrue($requests->accept($requester,$target));self::assertSame(0,$this->countType(SocialNotificationType::FollowRequest));self::assertSame(1,$this->countType(SocialNotificationType::FollowAccepted));self::assertTrue($follows->isFollowing($requester,$target));self::assertFalse($requests->accept($requester,$target));

        $public=$this->user('follow.public');$follower=$this->user('follow.public.actor');$follows->follow($follower,$public);$follows->follow($follower,$public);self::assertSame(1,$this->countType(SocialNotificationType::NewFollower));$follows->unfollow($follower,$public);$follows->follow($follower,$public);self::assertSame(2,$this->countType(SocialNotificationType::NewFollower));
    }

    public function testBatchAcceptLikesCommentsAndCascades():void
    {
        $actor=$this->user('activity.actor');$a=$this->user('activity.a');$b=$this->user('activity.b');$c=$this->user('activity.c');$requests=new UserFollowRequestRepository($this->database,$this->notifications);foreach([$a,$b,$c]as$requester){$requests->request($requester,$actor);}self::assertSame(3,$this->countType(SocialNotificationType::FollowRequest));
        $pdo=$this->database->connection();$pdo->beginTransaction();$requests->acceptAllForTarget($actor);$pdo->commit();$pdo->exec("UPDATE user_follows SET created_at='2026-11-02 00:00:00' WHERE followed_user_id={$actor}");self::assertSame(0,$this->countType(SocialNotificationType::FollowRequest));self::assertSame(3,$this->countType(SocialNotificationType::FollowAccepted));
        $this->share($actor,'2026-11-01 00:00:00');$history=$this->history($actor,'2026-11-03 00:00:00');$likes=new SocialActivityLikeRepository($this->database,$this->notifications);self::assertSame(SocialActivityLikeResult::Liked,$likes->likeVisibleActivity($a,$history));self::assertSame(SocialActivityLikeResult::AlreadyLiked,$likes->likeVisibleActivity($a,$history));self::assertSame(1,$this->countType(SocialNotificationType::ActivityLike));$likes->unlike($a,$history);self::assertSame(0,$this->countType(SocialNotificationType::ActivityLike));self::assertSame(SocialActivityLikeResult::Liked,$likes->likeVisibleActivity($a,$history));
        $comments=new SocialActivityCommentRepository($this->database,$this->notifications);[$body]=SocialCommentBody::validate('Great movie');self::assertSame(SocialActivityCommentCreateResult::Created,$comments->createAuthorized($a,$history,$body));self::assertSame(1,$this->countType(SocialNotificationType::ActivityComment));[$ownerBody]=SocialCommentBody::validate('Thanks');self::assertSame(SocialActivityCommentCreateResult::Created,$comments->createAuthorized($actor,$history,$ownerBody));self::assertSame(1,$this->countType(SocialNotificationType::ActivityComment));
        $commentId=(int)$pdo->query("SELECT id FROM social_activity_comments WHERE user_id={$a} LIMIT 1")->fetchColumn();$comments->deleteAuthorized($actor,$commentId);self::assertSame(0,$this->countType(SocialNotificationType::ActivityComment));$pdo->prepare('DELETE FROM watch_history WHERE id=?')->execute([$history]);self::assertSame(0,$this->countType(SocialNotificationType::ActivityLike));
        $this->notifications->newFollower($actor,$b);$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$b]);self::assertSame(0,(int)$pdo->query("SELECT COUNT(*) FROM social_notifications WHERE actor_user_id={$b}")->fetchColumn());$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$actor]);self::assertSame(0,(int)$pdo->query("SELECT COUNT(*) FROM social_notifications WHERE recipient_user_id={$actor}")->fetchColumn());
    }

    private function user(string$username):int{$id=$this->users->create($username,null);$this->profiles->create($id,$username,null);return$id;}
    private function share(int$userId,string$since):void{$this->database->connection()->prepare('UPDATE user_profiles SET share_watch_activity=1,share_watch_activity_since=? WHERE user_id=?')->execute([$since,$userId]);}
    private function history(int$actor,string$createdAt):int{$statement=$this->database->connection()->prepare("INSERT INTO watch_history(user_id,source,entry_type,source_id,title,watched_on,request_key,created_at) VALUES (?,'tmdb','movie',603,'Matrix','2026-01-01',?,?)");$statement->execute([$actor,md5($actor.$createdAt.random_int(1,PHP_INT_MAX)),$createdAt]);return(int)$this->database->connection()->lastInsertId();}
    private function countType(SocialNotificationType$type):int{$statement=$this->database->connection()->prepare('SELECT COUNT(*) FROM social_notifications WHERE type=?');$statement->execute([$type->value]);return(int)$statement->fetchColumn();}
    private function clear():void{$pdo=$this->database->connection();$pdo->exec('DELETE FROM social_notifications');$pdo->exec('DELETE FROM social_activity_comments');$pdo->exec('DELETE FROM social_activity_likes');$pdo->exec('DELETE FROM user_follow_requests');$pdo->exec('DELETE FROM user_follows');$pdo->exec('DELETE FROM watch_history');$pdo->exec('DELETE FROM user_external_identities');$pdo->exec('DELETE FROM user_profiles');$pdo->exec('DELETE FROM users');}
}
