<?php

declare(strict_types=1);

namespace App\Profiles;

use App\Core\Database;
use App\Social\FollowRequestStore;
use App\Social\WatchActivitySharingStore;
use Throwable;

final class ProfileUpdateService implements ProfileUpdater
{
    public function __construct(
        private readonly Database $database,
        private readonly ProfileStore $profiles,
        private readonly FollowRequestStore $followRequests,
        private readonly WatchActivitySharingStore $watchActivitySharing,
    ) {
    }

    public function update(
        int $userId,
        string $displayName,
        ?string $bio,
        bool $wasPrivate,
        bool $isPrivate,
        bool $shareWatchActivity,
    ): void {
        $pdo = $this->database->connection();
        $pdo->beginTransaction();
        try {
            $this->profiles->updateProfile($userId, $displayName, $bio, $isPrivate);
            $this->watchActivitySharing->setEnabled($userId, $shareWatchActivity);
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
