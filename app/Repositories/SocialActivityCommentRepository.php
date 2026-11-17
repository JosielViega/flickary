<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Social\SocialActivity;
use App\Social\SocialActivityAccess;
use App\Social\SocialActivityComment;
use App\Social\SocialActivityCommentCreateResult;
use App\Social\SocialActivityCommentPage;
use App\Social\SocialActivityCommentReader;
use App\Social\SocialActivityCommentStore;
use App\Social\SocialActivityDetail;
use App\Social\SocialActivityVisibility;
use App\Social\SocialCommentBody;
use App\Social\SocialOwnComment;
use App\Social\SocialOwnCommentPage;
use App\Notifications\SocialNotificationStore;
use PDO;
use Throwable;

final class SocialActivityCommentRepository implements SocialActivityCommentReader, SocialActivityCommentStore
{
    public function __construct(
        private readonly Database $database,
        private readonly ?SocialNotificationStore $notifications = null,
    ) {}

    public function detailForViewer(int $viewerUserId, int $historyId): ?SocialActivityDetail
    {
        if ($viewerUserId < 1 || $historyId < 1) {
            throw new \InvalidArgumentException('Invalid social activity detail identity.');
        }

        $statement = $this->database->connection()->prepare(
            'SELECT wh.id AS history_id, wh.user_id AS actor_user_id, u.username AS actor_username, '
            . 'p.display_name AS actor_display_name, p.avatar_url AS actor_avatar_url, '
            . 'wh.source, wh.entry_type, wh.source_id, wh.season_number, wh.episode_number, '
            . 'wh.title, wh.episode_title, wh.content_date, wh.poster_path, wh.is_anime, '
            . 'wh.watched_on, wh.created_at, COALESCE(lc.like_count, 0) AS like_count, '
            . 'CASE WHEN vl.user_id IS NULL THEN 0 ELSE 1 END AS viewer_liked, '
            . 'COALESCE(cc.comment_count, 0) AS comment_count, '
            . 'p.share_watch_activity, p.share_watch_activity_since, '
            . 'CASE WHEN wh.user_id = :access_owner_id THEN \'owner\' ELSE \'follower\' END AS access_type '
            . 'FROM watch_history wh '
            . 'INNER JOIN user_profiles p ON p.user_id = wh.user_id '
            . 'INNER JOIN users u ON u.id = wh.user_id '
            . 'LEFT JOIN user_follows f ON f.followed_user_id = wh.user_id AND f.follower_user_id = :follow_viewer_id '
            . 'LEFT JOIN (SELECT history_id, COUNT(*) AS like_count FROM social_activity_likes GROUP BY history_id) lc ON lc.history_id = wh.id '
            . 'LEFT JOIN social_activity_likes vl ON vl.history_id = wh.id AND vl.user_id = :like_viewer_id '
            . 'LEFT JOIN (SELECT history_id, COUNT(*) AS comment_count FROM social_activity_comments GROUP BY history_id) cc ON cc.history_id = wh.id '
            . 'WHERE wh.id = :history_id AND (wh.user_id = :owner_viewer_id OR (' . SocialActivityVisibility::SQL . ')) '
            . 'LIMIT 1',
        );
        $statement->execute([
            'access_owner_id' => $viewerUserId,
            'follow_viewer_id' => $viewerUserId,
            'like_viewer_id' => $viewerUserId,
            'history_id' => $historyId,
            'owner_viewer_id' => $viewerUserId,
            'viewer_user_id' => $viewerUserId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $access = $row['access_type'] === SocialActivityAccess::Owner->value
            ? SocialActivityAccess::Owner
            : SocialActivityAccess::Follower;
        $canComment = $access === SocialActivityAccess::Follower
            || ((int) $row['share_watch_activity'] === 1
                && is_string($row['share_watch_activity_since'])
                && (string) $row['created_at'] >= $row['share_watch_activity_since']);

        return new SocialActivityDetail($this->mapActivity($row), $access, $canComment);
    }

    public function paginateForActivity(int $historyId, int $page, int $perPage): SocialActivityCommentPage
    {
        $this->assertPagination($historyId, $page, $perPage);
        $count = $this->database->connection()->prepare(
            'SELECT COUNT(*) FROM social_activity_comments WHERE history_id = :history_id',
        );
        $count->execute(['history_id' => $historyId]);
        $total = (int) $count->fetchColumn();

        $statement = $this->database->connection()->prepare(
            'SELECT c.id,c.history_id,c.user_id,u.username,p.display_name,p.avatar_url,c.body,c.created_at '
            . 'FROM social_activity_comments c INNER JOIN users u ON u.id=c.user_id '
            . 'INNER JOIN user_profiles p ON p.user_id=c.user_id '
            . 'WHERE c.history_id=:history_id ORDER BY c.created_at DESC,c.id DESC LIMIT :limit OFFSET :offset',
        );
        $statement->bindValue(':history_id', $historyId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();

        $items = array_map(static fn (array $row): SocialActivityComment => new SocialActivityComment(
            (int) $row['id'],
            (int) $row['history_id'],
            (int) $row['user_id'],
            (string) $row['username'],
            (string) $row['display_name'],
            is_string($row['avatar_url']) ? $row['avatar_url'] : null,
            (string) $row['body'],
            (string) $row['created_at'],
        ), $statement->fetchAll(PDO::FETCH_ASSOC));

        return new SocialActivityCommentPage($items, $total, $page, $perPage);
    }

    public function paginateForAuthor(int $userId, int $page, int $perPage): SocialOwnCommentPage
    {
        $this->assertPagination($userId, $page, $perPage);
        $count = $this->database->connection()->prepare(
            'SELECT COUNT(*) FROM social_activity_comments WHERE user_id=:user_id',
        );
        $count->execute(['user_id' => $userId]);
        $total = (int) $count->fetchColumn();

        $statement = $this->database->connection()->prepare(
            'SELECT c.id,c.history_id,c.body,c.created_at,wh.title,wh.entry_type,wh.season_number,wh.episode_number,wh.episode_title,u.username AS actor_username '
            . 'FROM social_activity_comments c INNER JOIN watch_history wh ON wh.id=c.history_id '
            . 'INNER JOIN users u ON u.id=wh.user_id WHERE c.user_id=:user_id '
            . 'ORDER BY c.created_at DESC,c.id DESC LIMIT :limit OFFSET :offset',
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();

        $items = array_map(static fn (array $row): SocialOwnComment => new SocialOwnComment(
            (int) $row['id'],
            (int) $row['history_id'],
            (string) $row['body'],
            (string) $row['created_at'],
            (string) $row['title'],
            (string) $row['entry_type'],
            $row['season_number'] === null ? null : (int) $row['season_number'],
            $row['episode_number'] === null ? null : (int) $row['episode_number'],
            is_string($row['episode_title']) ? $row['episode_title'] : null,
            (string) $row['actor_username'],
        ), $statement->fetchAll(PDO::FETCH_ASSOC));

        return new SocialOwnCommentPage($items, $total, $page, $perPage);
    }

    public function createAuthorized(
        int $viewerUserId,
        int $historyId,
        SocialCommentBody $body,
    ): SocialActivityCommentCreateResult {
        if ($viewerUserId < 1 || $historyId < 1) {
            throw new \InvalidArgumentException('Invalid social activity comment identity.');
        }

        $pdo = $this->database->connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        $statement = $pdo->prepare(
            'INSERT INTO social_activity_comments(history_id,user_id,body) '
            . 'SELECT wh.id,:comment_user_id,:body FROM watch_history wh '
            . 'INNER JOIN user_profiles p ON p.user_id=wh.user_id '
            . 'LEFT JOIN user_follows f ON f.followed_user_id=wh.user_id AND f.follower_user_id=:follow_viewer_id '
            . 'WHERE wh.id=:history_id AND ('
            . '(wh.user_id=:owner_user_id AND p.share_watch_activity=1 AND p.share_watch_activity_since IS NOT NULL '
            . 'AND wh.created_at>=p.share_watch_activity_since) OR (' . SocialActivityVisibility::SQL . '))',
        );
        try {
            $statement->execute([
                'comment_user_id' => $viewerUserId,
                'body' => $body->value,
                'history_id' => $historyId,
                'owner_user_id' => $viewerUserId,
                'follow_viewer_id' => $viewerUserId,
                'viewer_user_id' => $viewerUserId,
            ]);
            $result = $statement->rowCount() === 1
                ? SocialActivityCommentCreateResult::Created
                : SocialActivityCommentCreateResult::Hidden;
            if ($result === SocialActivityCommentCreateResult::Created) {
                $this->notifications?->activityComment(
                    $historyId,
                    (int) $pdo->lastInsertId(),
                    $viewerUserId,
                );
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

    public function deleteAuthorized(int $viewerUserId, int $commentId): ?int
    {
        if ($viewerUserId < 1 || $commentId < 1) {
            throw new \InvalidArgumentException('Invalid social activity comment identity.');
        }

        $connection = $this->database->connection();
        $connection->beginTransaction();
        try {
            $statement = $connection->prepare(
                'SELECT c.history_id,c.user_id,wh.user_id AS actor_user_id FROM social_activity_comments c '
                . 'INNER JOIN watch_history wh ON wh.id=c.history_id WHERE c.id=:comment_id FOR UPDATE',
            );
            $statement->execute(['comment_id' => $commentId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)
                || ($viewerUserId !== (int) $row['user_id'] && $viewerUserId !== (int) $row['actor_user_id'])) {
                $connection->rollBack();
                return null;
            }

            $delete = $connection->prepare('DELETE FROM social_activity_comments WHERE id=:comment_id');
            $delete->execute(['comment_id' => $commentId]);
            $connection->commit();
            return (int) $row['history_id'];
        } catch (\Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $row */
    private function mapActivity(array $row): SocialActivity
    {
        return new SocialActivity(
            (int) $row['history_id'], (int) $row['actor_user_id'], (string) $row['actor_username'],
            (string) $row['actor_display_name'], is_string($row['actor_avatar_url']) ? $row['actor_avatar_url'] : null,
            (string) $row['source'], (string) $row['entry_type'], (int) $row['source_id'],
            $row['season_number'] === null ? null : (int) $row['season_number'],
            $row['episode_number'] === null ? null : (int) $row['episode_number'],
            (string) $row['title'], is_string($row['episode_title']) ? $row['episode_title'] : null,
            is_string($row['content_date']) ? $row['content_date'] : null,
            is_string($row['poster_path']) ? $row['poster_path'] : null,
            $row['is_anime'] === null ? null : (bool) (int) $row['is_anime'],
            (string) $row['watched_on'], (string) $row['created_at'], (int) $row['like_count'],
            (bool) (int) $row['viewer_liked'], (int) $row['comment_count'],
        );
    }

    private function assertPagination(int $identity, int $page, int $perPage): void
    {
        if ($identity < 1 || $page < 1 || $perPage < 1) {
            throw new \InvalidArgumentException('Invalid social comment pagination.');
        }
    }
}
