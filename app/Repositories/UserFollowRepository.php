<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Social\FollowStore;
use App\Social\PublicProfile;
use App\Social\PublicProfilePage;
use App\Notifications\SocialNotificationStore;
use PDO;
use Throwable;

final class UserFollowRepository implements FollowStore
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
            $statement = $pdo->prepare(
                'INSERT IGNORE INTO user_follows (follower_user_id,followed_user_id) '
                . 'VALUES (:follower,:followed)',
            );
            $statement->execute(['follower' => $followerUserId, 'followed' => $followedUserId]);
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
        $statement = $this->database->connection()->prepare(
            'DELETE FROM user_follows WHERE follower_user_id=:follower AND followed_user_id=:followed',
        );
        $statement->execute(['follower'=>$followerUserId,'followed'=>$followedUserId]);
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
        return $this->paginate($userId, $page, $perPage, true);
    }

    public function paginateFollowing(int $userId, int $page, int $perPage): PublicProfilePage
    {
        return $this->paginate($userId, $page, $perPage, false);
    }

    private function count(string $column, int $userId): int
    {
        $statement = $this->database->connection()->prepare('SELECT COUNT(*) FROM user_follows WHERE ' . $column . '=:user_id');
        $statement->execute(['user_id'=>$userId]);
        return (int) $statement->fetchColumn();
    }

    private function paginate(int $userId, int $page, int $perPage, bool $followers): PublicProfilePage
    {
        $page=max(1,$page); $perPage=max(1,min(100,$perPage));
        $filter = $followers ? 'f.followed_user_id=:user_id' : 'f.follower_user_id=:user_id';
        $joinedUser = $followers ? 'f.follower_user_id' : 'f.followed_user_id';
        $total = $followers ? $this->countFollowers($userId) : $this->countFollowing($userId);
        $statement=$this->database->connection()->prepare(
            'SELECT p.user_id,u.username,p.display_name,p.bio,p.avatar_url,p.cover_url,p.is_private,u.created_at '
            . 'FROM user_follows f INNER JOIN users u ON u.id=' . $joinedUser
            . ' INNER JOIN user_profiles p ON p.user_id=u.id WHERE ' . $filter
            . ' ORDER BY f.created_at DESC,' . $joinedUser . ' DESC LIMIT :limit OFFSET :offset',
        );
        $statement->bindValue(':user_id',$userId,PDO::PARAM_INT);
        $statement->bindValue(':limit',$perPage,PDO::PARAM_INT);
        $statement->bindValue(':offset',($page-1)*$perPage,PDO::PARAM_INT);
        $statement->execute();
        $items=array_map(static fn(array $row):PublicProfile=>PublicProfile::fromRow($row),$statement->fetchAll(PDO::FETCH_ASSOC));
        return new PublicProfilePage($items,$total,$page,$perPage);
    }
}
