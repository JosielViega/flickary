<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Social\FollowStore;
use App\Social\PublicProfile;
use App\Social\PublicProfilePage;
use App\Social\ViewerAwareFollowStore;
use App\Notifications\SocialNotificationStore;
use PDO;
use Throwable;

final class UserFollowRepository implements FollowStore, ViewerAwareFollowStore
{
    public function __construct(
        private readonly Database $database,
        private readonly ?SocialNotificationStore $notifications = null,
    )
    {
    }

    public function follow(int $followerUserId, int $followedUserId): void
    {
        $pdo = $this->database->connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $this->lockPair($pdo,$followerUserId,$followedUserId);
            $statement = $pdo->prepare(
                'INSERT IGNORE INTO user_follows (follower_user_id,followed_user_id) '
                . 'SELECT :follower,:followed WHERE NOT EXISTS (SELECT 1 FROM user_blocks '
                . 'WHERE (blocker_user_id=:follower_block AND blocked_user_id=:followed_block) '
                . 'OR (blocker_user_id=:followed_reverse AND blocked_user_id=:follower_reverse))',
            );
            $statement->execute(['follower'=>$followerUserId,'followed'=>$followedUserId,'follower_block'=>$followerUserId,
                'followed_block'=>$followedUserId,'followed_reverse'=>$followedUserId,'follower_reverse'=>$followerUserId]);
            if ($statement->rowCount() === 1) {
                $this->notifications?->newFollower($followedUserId, $followerUserId);
            }
            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function unfollow(int $followerUserId, int $followedUserId): void
    {
        $pdo=$this->database->connection(); $owns=!$pdo->inTransaction(); if($owns){$pdo->beginTransaction();}
        try {
            $this->lockPair($pdo,$followerUserId,$followedUserId);
            $statement=$pdo->prepare('DELETE FROM user_follows WHERE follower_user_id=:follower AND followed_user_id=:followed');
            $statement->execute(['follower'=>$followerUserId,'followed'=>$followedUserId]);
            $mute=$pdo->prepare('DELETE FROM user_mutes WHERE user_id=:follower AND muted_user_id=:followed');
            $mute->execute(['follower'=>$followerUserId,'followed'=>$followedUserId]);
            if($owns){$pdo->commit();}
        } catch(Throwable $exception){if($owns&&$pdo->inTransaction()){$pdo->rollBack();}throw $exception;}
    }

    public function isFollowing(int $followerUserId, int $followedUserId): bool
    {
        $statement = $this->database->connection()->prepare(
            'SELECT 1 FROM user_follows WHERE follower_user_id=:follower AND followed_user_id=:followed LIMIT 1',
        );
        $statement->execute(['follower'=>$followerUserId,'followed'=>$followedUserId]);
        return $statement->fetchColumn() !== false;
    }

    public function areMutualFollowers(int $userA, int $userB): bool
    {
        if ($userA < 1 || $userB < 1 || $userA === $userB) {
            return false;
        }
        $statement = $this->database->connection()->prepare(
            'SELECT COUNT(*) FROM user_follows WHERE '
            . '(follower_user_id=:user_a AND followed_user_id=:user_b) OR '
            . '(follower_user_id=:user_b_reverse AND followed_user_id=:user_a_reverse)',
        );
        $statement->execute([
            'user_a' => $userA,
            'user_b' => $userB,
            'user_b_reverse' => $userB,
            'user_a_reverse' => $userA,
        ]);
        return (int) $statement->fetchColumn() === 2;
    }

    public function countFollowers(int $userId): int { return $this->count('followed_user_id', $userId); }
    public function countFollowing(int $userId): int { return $this->count('follower_user_id', $userId); }

    public function paginateFollowers(int $userId, int $page, int $perPage): PublicProfilePage
    {
        return $this->paginate($userId, $page, $perPage, true, null);
    }

    public function paginateFollowing(int $userId, int $page, int $perPage): PublicProfilePage
    {
        return $this->paginate($userId, $page, $perPage, false, null);
    }

    public function paginateFollowersForViewer(int $userId,int $page,int $perPage,?int $viewerUserId):PublicProfilePage
    { return $this->paginate($userId,$page,$perPage,true,$viewerUserId); }
    public function paginateFollowingForViewer(int $userId,int $page,int $perPage,?int $viewerUserId):PublicProfilePage
    { return $this->paginate($userId,$page,$perPage,false,$viewerUserId); }

    private function count(string $column, int $userId): int
    {
        $statement = $this->database->connection()->prepare('SELECT COUNT(*) FROM user_follows WHERE ' . $column . '=:user_id');
        $statement->execute(['user_id'=>$userId]);
        return (int) $statement->fetchColumn();
    }

    private function paginate(int $userId, int $page, int $perPage, bool $followers, ?int $viewerUserId): PublicProfilePage
    {
        $page=max(1,$page); $perPage=max(1,min(100,$perPage));
        $filter = $followers ? 'f.followed_user_id=:user_id' : 'f.follower_user_id=:user_id';
        $joinedUser = $followers ? 'f.follower_user_id' : 'f.followed_user_id';
        $blockFilter=$viewerUserId===null?'':' AND NOT EXISTS (SELECT 1 FROM user_blocks b WHERE (b.blocker_user_id=:viewer_block AND b.blocked_user_id='.$joinedUser.') OR (b.blocker_user_id='.$joinedUser.' AND b.blocked_user_id=:viewer_block_reverse))';
        $count=$this->database->connection()->prepare('SELECT COUNT(*) FROM user_follows f WHERE '.$filter.$blockFilter);
        $count->bindValue(':user_id',$userId,PDO::PARAM_INT);
        if($viewerUserId!==null){$count->bindValue(':viewer_block',$viewerUserId,PDO::PARAM_INT);$count->bindValue(':viewer_block_reverse',$viewerUserId,PDO::PARAM_INT);}
        $count->execute(); $total=(int)$count->fetchColumn();
        $statement=$this->database->connection()->prepare(
            'SELECT p.user_id,u.username,p.display_name,p.bio,p.avatar_url,p.cover_url,p.is_private,u.created_at '
            . 'FROM user_follows f INNER JOIN users u ON u.id=' . $joinedUser
            . ' INNER JOIN user_profiles p ON p.user_id=u.id WHERE ' . $filter . $blockFilter
            . ' ORDER BY f.created_at DESC,' . $joinedUser . ' DESC LIMIT :limit OFFSET :offset',
        );
        $statement->bindValue(':user_id',$userId,PDO::PARAM_INT);
        if($viewerUserId!==null){$statement->bindValue(':viewer_block',$viewerUserId,PDO::PARAM_INT);$statement->bindValue(':viewer_block_reverse',$viewerUserId,PDO::PARAM_INT);}
        $statement->bindValue(':limit',$perPage,PDO::PARAM_INT);
        $statement->bindValue(':offset',($page-1)*$perPage,PDO::PARAM_INT);
        $statement->execute();
        $items=array_map(static fn(array $row):PublicProfile=>PublicProfile::fromRow($row),$statement->fetchAll(PDO::FETCH_ASSOC));
        return new PublicProfilePage($items,$total,$page,$perPage);
    }

    private function lockPair(PDO$pdo,int$userA,int$userB):void{$statement=$pdo->prepare('SELECT id FROM users WHERE id IN (:low,:high) ORDER BY id FOR UPDATE');$statement->execute(['low'=>min($userA,$userB),'high'=>max($userA,$userB)]);}
}
