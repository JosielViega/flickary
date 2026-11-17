<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Social\UserSocialControl;
use App\Social\UserSocialControlPage;
use App\Social\UserSocialControlStore;
use PDO;
use Throwable;

final class UserSocialControlRepository implements UserSocialControlStore
{
    public function __construct(private readonly Database $database) {}

    public function isBlockedEitherDirection(int $userA, int $userB): bool
    {
        if ($userA < 1 || $userB < 1 || $userA === $userB) { return false; }
        $statement = $this->database->connection()->prepare(
            'SELECT 1 FROM user_blocks WHERE (blocker_user_id=:a AND blocked_user_id=:b) '
            . 'OR (blocker_user_id=:b2 AND blocked_user_id=:a2) LIMIT 1',
        );
        $statement->execute(['a'=>$userA,'b'=>$userB,'b2'=>$userB,'a2'=>$userA]);
        return $statement->fetchColumn() !== false;
    }

    public function isBlockedBy(int $blockerUserId, int $blockedUserId): bool
    {
        $statement = $this->database->connection()->prepare(
            'SELECT 1 FROM user_blocks WHERE blocker_user_id=:blocker AND blocked_user_id=:blocked LIMIT 1',
        );
        $statement->execute(['blocker'=>$blockerUserId,'blocked'=>$blockedUserId]);
        return $statement->fetchColumn() !== false;
    }

    public function isMuted(int $userId, int $targetUserId): bool
    {
        $statement = $this->database->connection()->prepare(
            'SELECT 1 FROM user_mutes WHERE user_id=:user AND muted_user_id=:target LIMIT 1',
        );
        $statement->execute(['user'=>$userId,'target'=>$targetUserId]);
        return $statement->fetchColumn() !== false;
    }

    public function block(int $blockerUserId, int $blockedUserId): void
    {
        $this->assertPair($blockerUserId, $blockedUserId);
        $pdo = $this->database->connection();
        $pdo->beginTransaction();
        try {
            $this->lockUsers($blockerUserId, $blockedUserId);
            $insert = $pdo->prepare('INSERT IGNORE INTO user_blocks(blocker_user_id,blocked_user_id) VALUES (:a,:b)');
            $insert->execute(['a'=>$blockerUserId,'b'=>$blockedUserId]);
            foreach (['user_follows' => ['follower_user_id','followed_user_id'], 'user_follow_requests' => ['requester_user_id','target_user_id'], 'user_mutes' => ['user_id','muted_user_id']] as $table=>$columns) {
                [$left,$right] = $columns;
                $delete = $pdo->prepare("DELETE FROM {$table} WHERE ({$left}=:a AND {$right}=:b) OR ({$left}=:b2 AND {$right}=:a2)");
                $delete->execute(['a'=>$blockerUserId,'b'=>$blockedUserId,'b2'=>$blockedUserId,'a2'=>$blockerUserId]);
            }
            $notifications = $pdo->prepare(
                'DELETE FROM social_notifications WHERE (recipient_user_id=:a AND actor_user_id=:b) '
                . 'OR (recipient_user_id=:b2 AND actor_user_id=:a2)',
            );
            $notifications->execute(['a'=>$blockerUserId,'b'=>$blockedUserId,'b2'=>$blockedUserId,'a2'=>$blockerUserId]);
            $read = $pdo->prepare(
                'UPDATE direct_messages m INNER JOIN direct_conversations c ON c.id=m.conversation_id '
                . 'SET m.read_at=CURRENT_TIMESTAMP WHERE c.user_low_id=:low AND c.user_high_id=:high '
                . 'AND m.sender_user_id=:blocked AND m.read_at IS NULL',
            );
            $read->execute(['low'=>min($blockerUserId,$blockedUserId),'high'=>max($blockerUserId,$blockedUserId),'blocked'=>$blockedUserId]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $exception;
        }
    }

    public function unblock(int $blockerUserId, int $blockedUserId): void
    {
        $this->assertPair($blockerUserId, $blockedUserId);
        $statement=$this->database->connection()->prepare('DELETE FROM user_blocks WHERE blocker_user_id=:a AND blocked_user_id=:b');
        $statement->execute(['a'=>$blockerUserId,'b'=>$blockedUserId]);
    }

    public function mute(int $userId, int $targetUserId): bool
    {
        $this->assertPair($userId, $targetUserId);
        $pdo=$this->database->connection();$pdo->beginTransaction();
        try{$this->lockUsers($userId,$targetUserId);$statement=$pdo->prepare(
                'INSERT IGNORE INTO user_mutes(user_id,muted_user_id) SELECT :user,:target '
                . 'WHERE EXISTS (SELECT 1 FROM user_follows WHERE follower_user_id=:follower AND followed_user_id=:followed) '
                . 'AND NOT EXISTS (SELECT 1 FROM user_blocks WHERE (blocker_user_id=:a AND blocked_user_id=:b) OR (blocker_user_id=:b2 AND blocked_user_id=:a2))');
            $statement->execute(['user'=>$userId,'target'=>$targetUserId,'follower'=>$userId,'followed'=>$targetUserId,'a'=>$userId,'b'=>$targetUserId,'b2'=>$targetUserId,'a2'=>$userId]);$changed=$statement->rowCount()===1;
            if(!$changed){$check=$pdo->prepare('SELECT 1 FROM user_mutes WHERE user_id=:user AND muted_user_id=:target');$check->execute(['user'=>$userId,'target'=>$targetUserId]);$changed=$check->fetchColumn()!==false;}$pdo->commit();return$changed;
        }catch(Throwable$exception){if($pdo->inTransaction()){$pdo->rollBack();}throw$exception;}
    }

    public function unmute(int $userId, int $targetUserId): void
    {
        $this->assertPair($userId, $targetUserId);
        $statement=$this->database->connection()->prepare('DELETE FROM user_mutes WHERE user_id=:user AND muted_user_id=:target');
        $statement->execute(['user'=>$userId,'target'=>$targetUserId]);
    }

    public function paginateBlocked(int $userId, int $page, int $perPage): UserSocialControlPage
    { return $this->paginate('user_blocks','blocker_user_id','blocked_user_id',$userId,$page,$perPage); }

    public function paginateMuted(int $userId, int $page, int $perPage): UserSocialControlPage
    { return $this->paginate('user_mutes','user_id','muted_user_id',$userId,$page,$perPage); }

    private function paginate(string $table, string $owner, string $target, int $userId, int $page, int $perPage): UserSocialControlPage
    {
        $page=max(1,$page); $perPage=max(1,min(100,$perPage)); $pdo=$this->database->connection();
        $count=$pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$owner}=:user"); $count->execute(['user'=>$userId]);
        $statement=$pdo->prepare("SELECT u.id,u.username,p.display_name,p.avatar_url,c.created_at FROM {$table} c INNER JOIN users u ON u.id=c.{$target} INNER JOIN user_profiles p ON p.user_id=u.id WHERE c.{$owner}=:user ORDER BY c.created_at DESC,u.id DESC LIMIT :limit OFFSET :offset");
        $statement->bindValue(':user',$userId,PDO::PARAM_INT); $statement->bindValue(':limit',$perPage,PDO::PARAM_INT); $statement->bindValue(':offset',($page-1)*$perPage,PDO::PARAM_INT); $statement->execute();
        $items=array_map(static fn(array$row):UserSocialControl=>new UserSocialControl((int)$row['id'],(string)$row['username'],(string)$row['display_name'],is_string($row['avatar_url'])?$row['avatar_url']:null,(string)$row['created_at']),$statement->fetchAll(PDO::FETCH_ASSOC));
        return new UserSocialControlPage($items,(int)$count->fetchColumn(),$page,$perPage);
    }

    private function assertPair(int $userA, int $userB): void
    { if ($userA < 1 || $userB < 1 || $userA === $userB) { throw new \InvalidArgumentException('Invalid social control pair.'); } }

    private function lockUsers(int $userA, int $userB): void
    {
        $statement=$this->database->connection()->prepare('SELECT id FROM users WHERE id IN (:low,:high) ORDER BY id FOR UPDATE');
        $statement->execute(['low'=>min($userA,$userB),'high'=>max($userA,$userB)]);
        if (count($statement->fetchAll(PDO::FETCH_COLUMN)) !== 2) { throw new \InvalidArgumentException('Invalid social control target.'); }
    }
}
