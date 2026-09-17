<?php

declare(strict_types=1);

namespace App\Authentication;

use App\Core\Database;
use App\Repositories\ExternalIdentityRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use PDOException;

final class AccountOnboardingService implements AccountCreator
{
    public function __construct(
        private readonly Database $database,
        private readonly UserRepository $users,
        private readonly UserProfileRepository $profiles,
        private readonly ExternalIdentityRepository $externalIdentities,
    ) {
    }

    public function createFromExternalIdentity(ExternalIdentity $identity, string $username): AccountCreationResult
    {
        $email = $identity->emailVerified ? $identity->email : null;
        $connection = $this->database->connection();
        $connection->beginTransaction();

        try {
            $existingUserId = $this->externalIdentities->findUserId($identity->provider, $identity->providerUserId);
            if ($existingUserId !== null) {
                $connection->rollBack();
                return AccountCreationResult::identityExists($existingUserId);
            }

            if ($this->users->usernameExists($username)) {
                $connection->rollBack();
                return AccountCreationResult::usernameTaken();
            }

            if ($email !== null && $this->users->emailExists($email)) {
                $connection->rollBack();
                return AccountCreationResult::emailConflict();
            }

            $userId = $this->users->create($username, $email);
            $this->profiles->create(
                $userId,
                $identity->displayName ?? $username,
                $identity->avatarUrl,
            );
            $this->externalIdentities->create($userId, $identity->provider, $identity->providerUserId);
            $connection->commit();

            return AccountCreationResult::created($userId);
        } catch (\Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            if ($exception instanceof PDOException && $this->isUniqueConstraintViolation($exception)) {
                return $this->resolveConcurrentConflict($identity, $username, $email);
            }

            throw $exception;
        }
    }

    private function resolveConcurrentConflict(
        ExternalIdentity $identity,
        string $username,
        ?string $email,
    ): AccountCreationResult {
        $existingUserId = $this->externalIdentities->findUserId($identity->provider, $identity->providerUserId);
        if ($existingUserId !== null) {
            return AccountCreationResult::identityExists($existingUserId);
        }

        if ($this->users->usernameExists($username)) {
            return AccountCreationResult::usernameTaken();
        }

        if ($email !== null && $this->users->emailExists($email)) {
            return AccountCreationResult::emailConflict();
        }

        throw new \RuntimeException('Unable to resolve account uniqueness conflict.');
    }

    private function isUniqueConstraintViolation(PDOException $exception): bool
    {
        return ($exception->errorInfo[0] ?? (string) $exception->getCode()) === '23000';
    }
}
