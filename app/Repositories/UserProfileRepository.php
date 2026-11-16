<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Profiles\ProfileStore;
use App\Social\WatchActivitySharingStore;
use PDO;

final class UserProfileRepository implements ProfileStore, WatchActivitySharingStore
{
    public function __construct(private readonly Database $database)
    {
    }

    public function create(int $userId, string $displayName, ?string $avatarUrl): void
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO user_profiles (user_id, display_name, avatar_url) '
            . 'VALUES (:user_id, :display_name, :avatar_url)',
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':display_name', $displayName);
        $statement->bindValue(':avatar_url', $avatarUrl, $avatarUrl === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->execute();
    }

    public function findByUserId(int $userId): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT u.username, u.created_at, p.display_name, p.bio, p.avatar_url, p.cover_url, p.is_private, '
            . 'p.share_watch_activity, p.share_watch_activity_since '
            . 'FROM user_profiles p INNER JOIN users u ON u.id = p.user_id '
            . 'WHERE p.user_id = :user_id LIMIT 1',
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->execute();
        $profile = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($profile) ? $profile : null;
    }

    public function updateProfile(int $userId, string $displayName, ?string $bio, bool $isPrivate): void
    {
        $statement = $this->database->connection()->prepare(
            'UPDATE user_profiles SET display_name = :display_name, bio = :bio, is_private = :is_private '
            . 'WHERE user_id = :user_id',
        );
        $statement->bindValue(':display_name', $displayName);
        $statement->bindValue(':bio', $bio, $bio === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':is_private', $isPrivate ? 1 : 0, PDO::PARAM_INT);
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->execute();
    }

    public function setEnabled(int $userId, bool $enabled): void
    {
        if ($enabled) {
            $statement = $this->database->connection()->prepare(
                'UPDATE user_profiles SET share_watch_activity_since = '
                . 'CASE WHEN share_watch_activity = 1 THEN share_watch_activity_since ELSE CURRENT_TIMESTAMP END, '
                . 'share_watch_activity = 1 WHERE user_id = :user_id',
            );
        } else {
            $statement = $this->database->connection()->prepare(
                'UPDATE user_profiles SET share_watch_activity = 0, share_watch_activity_since = NULL '
                . 'WHERE user_id = :user_id',
            );
        }
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->execute();
    }
}
