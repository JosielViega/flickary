<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Authentication\ExternalIdentityFinder;
use App\Core\Database;
use PDO;

final class ExternalIdentityRepository implements ExternalIdentityFinder
{
    public function __construct(private readonly Database $database)
    {
    }

    public function findUserId(string $provider, string $providerUserId): ?int
    {
        $statement = $this->database->connection()->prepare(
            'SELECT user_id FROM user_external_identities '
            . 'WHERE provider = :provider AND provider_user_id = :provider_user_id LIMIT 1',
        );
        $statement->execute([
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
        ]);
        $userId = $statement->fetchColumn();

        return $userId === false ? null : (int) $userId;
    }

    public function create(int $userId, string $provider, string $providerUserId): void
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO user_external_identities (user_id, provider, provider_user_id) '
            . 'VALUES (:user_id, :provider, :provider_user_id)',
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':provider', $provider);
        $statement->bindValue(':provider_user_id', $providerUserId);
        $statement->execute();
    }
}
