<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Social\PublicFavorite;
use App\Social\PublicProfile;
use App\Social\PublicProfilePage;
use App\Social\PublicProfileReader;
use App\Social\PublicProfileStatistics;
use App\Social\ViewerAwarePublicProfileReader;
use PDO;

final class PublicProfileRepository implements PublicProfileReader, ViewerAwarePublicProfileReader
{
    public function __construct(private readonly Database $database)
    {
    }

    public function findByUsername(string $username): ?PublicProfile
    {
        $statement = $this->database->connection()->prepare(
            'SELECT p.user_id,u.username,p.display_name,p.bio,p.avatar_url,p.cover_url,p.is_private,u.created_at '
            . 'FROM users u INNER JOIN user_profiles p ON p.user_id=u.id WHERE u.username=:username LIMIT 1',
        );
        $statement->execute(['username' => $username]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? PublicProfile::fromRow($row) : null;
    }

    public function statisticsForUser(int $userId): PublicProfileStatistics
    {
        $statement = $this->database->connection()->prepare(
            'SELECT '
            . '(SELECT COUNT(*) FROM user_media WHERE user_id=:media_user) library_items,'
            . '(SELECT COUNT(*) FROM user_media_preferences WHERE user_id=:favorite_user AND is_favorite=1) favorite_items,'
            . '(SELECT COUNT(*) FROM user_media_preferences WHERE user_id=:rated_user AND rating IS NOT NULL) rated_items,'
            . '(SELECT AVG(rating) FROM user_media_preferences WHERE user_id=:average_user AND rating IS NOT NULL) average_rating,'
            . '(SELECT COUNT(*) FROM watch_history WHERE user_id=:history_user) history_views,'
            . '(SELECT COALESCE(SUM(duration_minutes),0) FROM watch_history WHERE user_id=:minutes_user) known_minutes,'
            . '(SELECT COUNT(*) FROM watch_history WHERE user_id=:unknown_user AND duration_minutes IS NULL) unknown_duration_views',
        );
        $statement->execute([
            'media_user'=>$userId,'favorite_user'=>$userId,'rated_user'=>$userId,'average_user'=>$userId,
            'history_user'=>$userId,'minutes_user'=>$userId,'unknown_user'=>$userId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
        return new PublicProfileStatistics(
            (int) ($row['library_items'] ?? 0), (int) ($row['favorite_items'] ?? 0),
            (int) ($row['rated_items'] ?? 0), $row['average_rating'] === null ? null : (float) $row['average_rating'],
            (int) ($row['history_views'] ?? 0), (int) ($row['known_minutes'] ?? 0),
            (int) ($row['unknown_duration_views'] ?? 0),
        );
    }

    public function recentFavorites(int $userId, int $limit = 6): array
    {
        $limit = max(1, min(6, $limit));
        $statement = $this->database->connection()->prepare(
            'SELECT media_type,source_id,title,original_title,release_date,poster_path,is_anime,rating '
            . 'FROM user_media_preferences WHERE user_id=:user_id AND is_favorite=1 '
            . 'ORDER BY updated_at DESC,id DESC LIMIT :limit',
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return array_map(static fn (array $row): PublicFavorite => new PublicFavorite(
            (string) $row['media_type'], (int) $row['source_id'], (string) $row['title'],
            is_string($row['original_title']) ? $row['original_title'] : null,
            is_string($row['release_date']) ? $row['release_date'] : null,
            is_string($row['poster_path']) ? $row['poster_path'] : null,
            $row['is_anime'] === null ? null : (bool) (int) $row['is_anime'],
            $row['rating'] === null ? null : (int) $row['rating'],
        ), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function search(string $query, int $page, int $perPage): PublicProfilePage
    { return $this->searchForViewer($query,$page,$perPage,null); }

    public function searchForViewer(string $query, int $page, int $perPage, ?int $viewerUserId): PublicProfilePage
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
        $contains = '%' . $escaped . '%';
        $prefix = $escaped . '%';
        $where = "(u.username LIKE :contains ESCAPE '\\\\' OR p.display_name LIKE :display_contains ESCAPE '\\\\')";
        if($viewerUserId!==null){$where.=' AND NOT EXISTS (SELECT 1 FROM user_blocks b WHERE (b.blocker_user_id=:viewer_block AND b.blocked_user_id=u.id) OR (b.blocker_user_id=u.id AND b.blocked_user_id=:viewer_block_reverse))';}
        $count = $this->database->connection()->prepare(
            'SELECT COUNT(*) FROM users u INNER JOIN user_profiles p ON p.user_id=u.id WHERE ' . $where,
        );
        $countParams=['contains'=>$contains,'display_contains'=>$contains]; if($viewerUserId!==null){$countParams['viewer_block']=$viewerUserId;$countParams['viewer_block_reverse']=$viewerUserId;} $count->execute($countParams);
        $total = (int) $count->fetchColumn();
        $statement = $this->database->connection()->prepare(
            'SELECT p.user_id,u.username,p.display_name,p.bio,p.avatar_url,p.cover_url,p.is_private,u.created_at '
            . 'FROM users u INNER JOIN user_profiles p ON p.user_id=u.id WHERE ' . $where
            . ' ORDER BY CASE WHEN u.username=:exact THEN 0 WHEN u.username LIKE :prefix ESCAPE \'\\\\\' THEN 1 '
            . 'WHEN p.display_name LIKE :display_prefix ESCAPE \'\\\\\' THEN 2 ELSE 3 END,u.username ASC '
            . 'LIMIT :limit OFFSET :offset',
        );
        foreach (['contains'=>$contains,'display_contains'=>$contains,'exact'=>$query,'prefix'=>$prefix,'display_prefix'=>$prefix] as $key=>$value) {
            $statement->bindValue(':' . $key, $value);
        }
        if($viewerUserId!==null){$statement->bindValue(':viewer_block',$viewerUserId,PDO::PARAM_INT);$statement->bindValue(':viewer_block_reverse',$viewerUserId,PDO::PARAM_INT);}
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();
        $items = array_map(static fn (array $row): PublicProfile => PublicProfile::fromRow($row), $statement->fetchAll(PDO::FETCH_ASSOC));
        return new PublicProfilePage($items, $total, $page, $perPage);
    }
}
