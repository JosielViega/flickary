<?php

declare(strict_types=1);

namespace App\Profiles;

use App\Core\Database;
use App\Social\FollowRequestStore;
use Throwable;

final class ProfileUpdateService implements ProfileUpdater
{
    public function __construct(
        private readonly Database $database,
        private readonly ProfileStore $profiles,
        private readonly FollowRequestStore $followRequests,
    ) {
    }

    public function update(
        int $userId,
        string $displayName,
        ?string $bio,
        bool $wasPrivate,
        bool $isPrivate,
    ): void {
        $pdo = $this->database->connection();
        $pdo->beginTransaction();
        try {
            $this->profiles->updateProfile($userId, $displayName, $bio, $isPrivate);
            if ($wasPrivate && !$isPrivate) {
                $this->followRequests->acceptAllForTarget($userId);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}
