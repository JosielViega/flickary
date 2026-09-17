<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class UserRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    public function usernameExists(string $username): bool
    {
        $statement = $this->database->connection()->prepare(
            'SELECT 1 FROM users WHERE username = :username LIMIT 1',
        );
        $statement->execute(['username' => $username]);

        return $statement->fetchColumn() !== false;
    }

    public function emailExists(string $email): bool
    {
        $statement = $this->database->connection()->prepare(
            'SELECT 1 FROM users WHERE email = :email LIMIT 1',
        );
        $statement->execute(['email' => $email]);

        return $statement->fetchColumn() !== false;
    }

    public function create(string $username, ?string $email): int
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO users (username, email) VALUES (:username, :email)',
        );
        $statement->bindValue(':username', $username);
        $statement->bindValue(':email', $email, $email === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->execute();

        return (int) $this->database->connection()->lastInsertId();
    }
}
