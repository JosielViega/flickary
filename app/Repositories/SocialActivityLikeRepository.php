<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Notifications\SocialNotificationStore;
use App\Social\SocialActivityLikeResult;
use App\Social\SocialActivityLikeStore;
use App\Social\SocialActivityVisibility;
use PDOException;
use Throwable;

final class SocialActivityLikeRepository implements SocialActivityLikeStore
{
    public function __construct(
        private readonly Database $database,
        private readonly ?SocialNotificationStore $notifications = null,
    ) {}

    public function likeVisibleActivity(int $viewerUserId, int $historyId): SocialActivityLikeResult
    {
        if ($viewerUserId < 1 || $historyId < 1) {
            throw new \InvalidArgumentException('Invalid social activity like identity.');
        }

        $pdo = $this->database->connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $statement = $pdo->prepare(
                'INSERT INTO social_activity_likes(history_id,user_id) '
                . 'SELECT wh.id,:liker FROM watch_history wh '
                . 'INNER JOIN user_profiles p ON p.user_id=wh.user_id '
                . 'INNER JOIN user_follows f ON f.followed_user_id=wh.user_id '
                . 'WHERE wh.id=:history AND ' . SocialActivityVisibility::SQL,
            );
            try {
                $statement->execute([
                    'liker' => $viewerUserId,
                    'viewer_user_id' => $viewerUserId,
                    'history' => $historyId,
                ]);
            } catch (PDOException $exception) {
                if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                    if ($ownsTransaction) {
                        $pdo->commit();
                    }
                    return SocialActivityLikeResult::AlreadyLiked;
                }
                throw $exception;
            }

            $result = $statement->rowCount() === 1
                ? SocialActivityLikeResult::Liked
                : SocialActivityLikeResult::Hidden;
            if ($result === SocialActivityLikeResult::Liked) {
                $this->notifications?->activityLike($historyId, $viewerUserId);
            }
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return $result;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function unlike(int $viewerUserId, int $historyId): void
    {
        if ($viewerUserId < 1 || $historyId < 1) {
            throw new \InvalidArgumentException('Invalid social activity like identity.');
        }

        $pdo = $this->database->connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $statement = $pdo->prepare(
                'DELETE FROM social_activity_likes WHERE history_id=:history AND user_id=:user',
            );
            $statement->execute(['history' => $historyId, 'user' => $viewerUserId]);
            if ($statement->rowCount() > 0) {
                $this->notifications?->removeActivityLike($historyId, $viewerUserId);
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
}
