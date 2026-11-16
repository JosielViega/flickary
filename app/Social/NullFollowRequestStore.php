<?php

declare(strict_types=1);

namespace App\Social;

final class NullFollowRequestStore implements FollowRequestStore
{
    public function request(int $requesterUserId, int $targetUserId): void {}
    public function cancel(int $requesterUserId, int $targetUserId): void {}
    public function isPending(int $requesterUserId, int $targetUserId): bool { return false; }
    public function countIncoming(int $targetUserId): int { return 0; }
    public function paginateIncoming(int $targetUserId, int $page, int $perPage): FollowRequestPage { return new FollowRequestPage([], 0, $page, $perPage); }
    public function accept(int $requesterUserId, int $targetUserId): bool { return false; }
    public function reject(int $requesterUserId, int $targetUserId): bool { return false; }
    public function acceptAllForTarget(int $targetUserId): void {}
}
