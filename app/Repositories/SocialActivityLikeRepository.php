<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Social\SocialActivityLikeResult;
use App\Social\SocialActivityLikeStore;
use App\Social\SocialActivityVisibility;
use PDOException;

final class SocialActivityLikeRepository implements SocialActivityLikeStore
{
    public function __construct(private readonly Database $database)
    {
    }

    public function likeVisibleActivity(int $viewerUserId, int $historyId): SocialActivityLikeResult
    {
        if ($viewerUserId < 1 || $historyId < 1) {
            throw new \InvalidArgumentException('Invalid social activity like identity.');
        }

        $statement = $this->database->connection()->prepare(
            'INSERT INTO social_activity_likes (history_id, user_id) '
            . 'SELECT wh.id, :liker_user_id FROM watch_history wh '
            . 'INNER JOIN user_profiles p ON p.user_id = wh.user_id '
            . 'INNER JOIN user_follows f ON f.followed_user_id = wh.user_id '
            . 'WHERE wh.id = :history_id AND ' . SocialActivityVisibility::SQL,
        );

        try {
            $statement->execute([
                'liker_user_id' => $viewerUserId,
                'viewer_user_id' => $viewerUserId,
                'history_id' => $historyId,
            ]);
        } catch (PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                return SocialActivityLikeResult::AlreadyLiked;
            }

            throw $exception;
        }

        return $statement->rowCount() === 1
            ? SocialActivityLikeResult::Liked
            : SocialActivityLikeResult::Hidden;
    }

    public function unlike(int $viewerUserId, int $historyId): void
    {
        if ($viewerUserId < 1 || $historyId < 1) {
            throw new \InvalidArgumentException('Invalid social activity like identity.');
        }

        $statement = $this->database->connection()->prepare(
            'DELETE FROM social_activity_likes WHERE history_id = :history_id AND user_id = :user_id',
        );
        $statement->execute(['history_id' => $historyId, 'user_id' => $viewerUserId]);
    }
}
