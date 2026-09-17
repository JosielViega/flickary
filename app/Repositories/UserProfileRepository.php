<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class UserProfileRepository
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
}
