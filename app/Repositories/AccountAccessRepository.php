<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Moderation\AccountAccessReader;
use PDO;

final class AccountAccessRepository implements AccountAccessReader
{
    public function __construct(private readonly Database $database) {}

    public function activeSuspensionUntil(int $userId): ?string
    {
        if ($userId < 1) { return null; }
        $statement = $this->database->connection()->prepare(
            'SELECT suspended_until FROM users WHERE id=:id AND suspended_until>CURRENT_TIMESTAMP LIMIT 1',
        );
        $statement->execute(['id' => $userId]);
        $value = $statement->fetchColumn();

        return is_string($value) ? $value : null;
    }
}
