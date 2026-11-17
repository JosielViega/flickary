<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Administration\AdminUserReader;
use App\Core\Database;
use PDO;

final class AdminUserRepository implements AdminUserReader
{
    public function __construct(private readonly Database $database)
    {
    }

    public function isAdmin(int $userId): bool
    {
        if ($userId < 1) {
            return false;
        }
        $statement = $this->database->connection()->prepare(
            'SELECT is_admin FROM users WHERE id=:user_id LIMIT 1',
        );
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn() === 1;
    }

    public function setAdmin(string $username, bool $isAdmin): bool
    {
        $statement = $this->database->connection()->prepare(
            'UPDATE users SET is_admin=:is_admin WHERE username=:username',
        );
        $statement->bindValue(':is_admin', $isAdmin, PDO::PARAM_BOOL);
        $statement->bindValue(':username', $username);
        $statement->execute();
        if ($statement->rowCount() > 0) {
            return true;
        }
        $exists = $this->database->connection()->prepare('SELECT 1 FROM users WHERE username=:username');
        $exists->execute(['username' => $username]);

        return $exists->fetchColumn() !== false;
    }

    public function adminUsernames(): array
    {
        $statement = $this->database->connection()->query(
            'SELECT username FROM users WHERE is_admin=1 ORDER BY username ASC',
        );

        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }
}
