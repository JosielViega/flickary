<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Social\SocialActivity;
use App\Social\SocialFeedPage;
use App\Social\SocialFeedReader;
use PDO;

final class SocialFeedRepository implements SocialFeedReader
{
    private const VISIBILITY = 'f.follower_user_id = :viewer_user_id '
        . 'AND p.share_watch_activity = 1 '
        . 'AND p.share_watch_activity_since IS NOT NULL '
        . 'AND wh.created_at >= p.share_watch_activity_since '
        . 'AND wh.created_at >= f.created_at';

    public function __construct(private readonly Database $database)
    {
    }

    public function paginateForViewer(int $viewerUserId, int $page, int $perPage): SocialFeedPage
    {
        if ($viewerUserId < 1 || $page < 1 || $perPage < 1) {
            throw new \InvalidArgumentException('Invalid social feed pagination.');
        }

        $from = ' FROM user_follows f '
            . 'INNER JOIN user_profiles p ON p.user_id = f.followed_user_id '
            . 'INNER JOIN users u ON u.id = p.user_id '
            . 'INNER JOIN watch_history wh ON wh.user_id = p.user_id '
            . 'WHERE ' . self::VISIBILITY;

        $count = $this->database->connection()->prepare('SELECT COUNT(*)' . $from);
        $count->bindValue(':viewer_user_id', $viewerUserId, PDO::PARAM_INT);
        $count->execute();
        $total = (int) $count->fetchColumn();

        $statement = $this->database->connection()->prepare(
            'SELECT wh.id AS history_id, p.user_id AS actor_user_id, u.username AS actor_username, '
            . 'p.display_name AS actor_display_name, p.avatar_url AS actor_avatar_url, '
            . 'wh.source, wh.entry_type, wh.source_id, wh.season_number, wh.episode_number, '
            . 'wh.title, wh.episode_title, wh.content_date, wh.poster_path, wh.is_anime, '
            . 'wh.watched_on, wh.created_at' . $from
            . ' ORDER BY wh.created_at DESC, wh.id DESC LIMIT :limit OFFSET :offset',
        );
        $statement->bindValue(':viewer_user_id', $viewerUserId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();

        $items = array_map(
            static fn (array $row): SocialActivity => new SocialActivity(
                (int) $row['history_id'],
                (int) $row['actor_user_id'],
                (string) $row['actor_username'],
                (string) $row['actor_display_name'],
                is_string($row['actor_avatar_url']) ? $row['actor_avatar_url'] : null,
                (string) $row['source'],
                (string) $row['entry_type'],
                (int) $row['source_id'],
                $row['season_number'] === null ? null : (int) $row['season_number'],
                $row['episode_number'] === null ? null : (int) $row['episode_number'],
                (string) $row['title'],
                is_string($row['episode_title']) ? $row['episode_title'] : null,
                is_string($row['content_date']) ? $row['content_date'] : null,
                is_string($row['poster_path']) ? $row['poster_path'] : null,
                $row['is_anime'] === null ? null : (bool) (int) $row['is_anime'],
                (string) $row['watched_on'],
                (string) $row['created_at'],
            ),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );

        return new SocialFeedPage($items, $total, $page, $perPage);
    }
}
