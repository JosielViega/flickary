<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Authentication\ExternalIdentityFinder;
use App\Authentication\ExternalIdentity;
use App\Authentication\ExternalIdentityLinker;
use App\Authentication\ConnectedProviderReader;
use App\Core\Database;
use PDO;

final class ExternalIdentityRepository implements ExternalIdentityFinder, ExternalIdentityLinker, ConnectedProviderReader
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

    public function providersForUser(int $userId): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT provider FROM user_external_identities WHERE user_id = :user_id ORDER BY provider',
        );
        $statement->execute(['user_id' => $userId]);

        return array_values(array_filter(
            $statement->fetchAll(PDO::FETCH_COLUMN),
            static fn (mixed $provider): bool => is_string($provider) && $provider !== '',
        ));
    }

    public function link(int $userId, ExternalIdentity $identity): string
    {
        $owner = $this->findUserId($identity->provider, $identity->providerUserId);
        if ($owner === $userId) {
            return self::ALREADY_LINKED;
        }
        if ($owner !== null || in_array($identity->provider, $this->providersForUser($userId), true)) {
            return self::CONFLICT;
        }

        try {
            $this->create($userId, $identity->provider, $identity->providerUserId);
            return self::LINKED;
        } catch (\PDOException $exception) {
            if (($exception->errorInfo[0] ?? (string) $exception->getCode()) !== '23000') {
                throw $exception;
            }

            return $this->findUserId($identity->provider, $identity->providerUserId) === $userId
                ? self::ALREADY_LINKED
                : self::CONFLICT;
        }
    }
}
