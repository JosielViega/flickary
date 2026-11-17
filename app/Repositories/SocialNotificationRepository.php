<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Notifications\SocialNotification;
use App\Notifications\SocialNotificationPage;
use App\Notifications\SocialNotificationReader;
use App\Notifications\SocialNotificationStore;
use App\Notifications\SocialNotificationType;
use PDO;

final class SocialNotificationRepository implements SocialNotificationReader, SocialNotificationStore
{
    public function __construct(private readonly Database $database) {}

    public function paginateForUser(int $userId, int $page, int $perPage): SocialNotificationPage
    {
        $this->assertIdentity($userId);
        if ($page < 1 || $perPage < 1) { throw new \InvalidArgumentException('Invalid notification pagination.'); }
        $count = $this->database->connection()->prepare('SELECT COUNT(*) FROM social_notifications WHERE recipient_user_id=:recipient');
        $count->execute(['recipient'=>$userId]);
        $statement = $this->database->connection()->prepare(
            'SELECT n.id,n.type,n.actor_user_id,u.username,p.display_name,p.avatar_url,n.history_id,n.comment_id,n.read_at,n.created_at '
            . 'FROM social_notifications n INNER JOIN users u ON u.id=n.actor_user_id '
            . 'INNER JOIN user_profiles p ON p.user_id=u.id WHERE n.recipient_user_id=:recipient '
            . 'ORDER BY n.created_at DESC,n.id DESC LIMIT :limit OFFSET :offset',
        );
        $statement->bindValue(':recipient',$userId,PDO::PARAM_INT);
        $statement->bindValue(':limit',$perPage,PDO::PARAM_INT);
        $statement->bindValue(':offset',($page-1)*$perPage,PDO::PARAM_INT);
        $statement->execute();
        return new SocialNotificationPage(
            array_map(fn(array $row):SocialNotification=>$this->map($row),$statement->fetchAll(PDO::FETCH_ASSOC)),
            (int)$count->fetchColumn(),$page,$perPage,
        );
    }

    public function countUnread(int $userId): int
    {
        $this->assertIdentity($userId);
        $statement=$this->database->connection()->prepare('SELECT COUNT(*) FROM social_notifications WHERE recipient_user_id=:recipient AND read_at IS NULL');
        $statement->execute(['recipient'=>$userId]);
        return (int)$statement->fetchColumn();
    }

    public function findForRecipient(int $recipientUserId, int $notificationId): ?SocialNotification
    {
        $this->assertIdentity($recipientUserId,$notificationId);
        $statement=$this->database->connection()->prepare(
            'SELECT n.id,n.type,n.actor_user_id,u.username,p.display_name,p.avatar_url,n.history_id,n.comment_id,n.read_at,n.created_at '
            . 'FROM social_notifications n INNER JOIN users u ON u.id=n.actor_user_id INNER JOIN user_profiles p ON p.user_id=u.id '
            . 'WHERE n.id=:id AND n.recipient_user_id=:recipient LIMIT 1',
        );
        $statement->execute(['id'=>$notificationId,'recipient'=>$recipientUserId]);
        $row=$statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row)?$this->map($row):null;
    }

    public function markRead(int $recipientUserId, int $notificationId): bool
    {
        $this->assertIdentity($recipientUserId,$notificationId);
        $statement=$this->database->connection()->prepare(
            'UPDATE social_notifications SET read_at=COALESCE(read_at,CURRENT_TIMESTAMP) WHERE id=:id AND recipient_user_id=:recipient',
        );
        $statement->execute(['id'=>$notificationId,'recipient'=>$recipientUserId]);
        return $statement->rowCount()>0 || $this->exists($recipientUserId,$notificationId);
    }

    public function markAllRead(int $recipientUserId): void
    {
        $this->assertIdentity($recipientUserId);
        $statement=$this->database->connection()->prepare('UPDATE social_notifications SET read_at=CURRENT_TIMESTAMP WHERE recipient_user_id=:recipient AND read_at IS NULL');
        $statement->execute(['recipient'=>$recipientUserId]);
    }

    public function followRequest(int $recipientUserId,int $actorUserId):void{$this->insert($recipientUserId,$actorUserId,SocialNotificationType::FollowRequest);}
    public function followAccepted(int $recipientUserId,int $actorUserId):void{$this->insert($recipientUserId,$actorUserId,SocialNotificationType::FollowAccepted);}
    public function newFollower(int $recipientUserId,int $actorUserId):void{$this->insert($recipientUserId,$actorUserId,SocialNotificationType::NewFollower);}

    public function removeFollowRequest(int $recipientUserId,int $actorUserId):void
    {
        $this->deleteByTypeActor(SocialNotificationType::FollowRequest,$recipientUserId,$actorUserId);
    }

    public function followAcceptedForPendingTarget(int $targetUserId): void
    {
        $this->assertIdentity($targetUserId);
        $statement=$this->database->connection()->prepare(
            'INSERT INTO social_notifications(recipient_user_id,actor_user_id,type,created_at) '
            . 'SELECT requester_user_id,target_user_id,:type,CURRENT_TIMESTAMP FROM user_follow_requests WHERE target_user_id=:target',
        );
        $statement->execute(['type'=>SocialNotificationType::FollowAccepted->value,'target'=>$targetUserId]);
    }

    public function removeFollowRequestsForTarget(int $targetUserId): void
    {
        $this->assertIdentity($targetUserId);
        $statement=$this->database->connection()->prepare('DELETE FROM social_notifications WHERE recipient_user_id=:target AND type=:type');
        $statement->execute(['target'=>$targetUserId,'type'=>SocialNotificationType::FollowRequest->value]);
    }

    public function activityLike(int $historyId,int $actorUserId):void
    {
        $this->insertActivity(SocialNotificationType::ActivityLike,$historyId,null,$actorUserId);
    }

    public function removeActivityLike(int $historyId,int $actorUserId):void
    {
        $statement=$this->database->connection()->prepare('DELETE FROM social_notifications WHERE type=:type AND history_id=:history AND actor_user_id=:actor');
        $statement->execute(['type'=>SocialNotificationType::ActivityLike->value,'history'=>$historyId,'actor'=>$actorUserId]);
    }

    public function activityComment(int $historyId,int $commentId,int $actorUserId):void
    {
        $this->insertActivity(SocialNotificationType::ActivityComment,$historyId,$commentId,$actorUserId);
    }

    private function insert(int $recipient,int $actor,SocialNotificationType $type):void
    {
        $this->assertIdentity($recipient,$actor);
        if($recipient===$actor){return;}
        $statement=$this->database->connection()->prepare('INSERT INTO social_notifications(recipient_user_id,actor_user_id,type) VALUES (:recipient,:actor,:type)');
        $statement->execute(['recipient'=>$recipient,'actor'=>$actor,'type'=>$type->value]);
    }

    private function insertActivity(SocialNotificationType $type,int $historyId,?int $commentId,int $actor):void
    {
        $this->assertIdentity($historyId,$actor);
        $statement=$this->database->connection()->prepare(
            'INSERT INTO social_notifications(recipient_user_id,actor_user_id,type,history_id,comment_id) '
            . 'SELECT wh.user_id,:actor,:type,wh.id,:comment FROM watch_history wh WHERE wh.id=:history AND wh.user_id<>:owner_actor',
        );
        $statement->execute(['actor'=>$actor,'type'=>$type->value,'comment'=>$commentId,'history'=>$historyId,'owner_actor'=>$actor]);
    }

    private function deleteByTypeActor(SocialNotificationType$type,int$recipient,int$actor):void
    {
        $statement=$this->database->connection()->prepare('DELETE FROM social_notifications WHERE recipient_user_id=:recipient AND actor_user_id=:actor AND type=:type');
        $statement->execute(['recipient'=>$recipient,'actor'=>$actor,'type'=>$type->value]);
    }

    private function exists(int$recipient,int$id):bool
    {
        $statement=$this->database->connection()->prepare('SELECT 1 FROM social_notifications WHERE id=:id AND recipient_user_id=:recipient');
        $statement->execute(['id'=>$id,'recipient'=>$recipient]);
        return $statement->fetchColumn()!==false;
    }

    private function map(array$row):SocialNotification
    {
        $type=SocialNotificationType::tryFrom((string)$row['type']);
        if(!$type instanceof SocialNotificationType){throw new \UnexpectedValueException('Unknown social notification type.');}
        return new SocialNotification((int)$row['id'],$type,(int)$row['actor_user_id'],(string)$row['username'],(string)$row['display_name'],is_string($row['avatar_url'])?$row['avatar_url']:null,$row['history_id']===null?null:(int)$row['history_id'],$row['comment_id']===null?null:(int)$row['comment_id'],is_string($row['read_at'])?$row['read_at']:null,(string)$row['created_at']);
    }

    private function assertIdentity(int ...$ids):void
    {
        foreach($ids as$id){if($id<1){throw new \InvalidArgumentException('Invalid notification identity.');}}
    }
}
