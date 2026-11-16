<?php

declare(strict_types=1);

namespace App\Social;

interface FollowRequestStore
{
    public function request(int $requesterUserId, int $targetUserId): void;
    public function cancel(int $requesterUserId, int $targetUserId): void;
    public function isPending(int $requesterUserId, int $targetUserId): bool;
    public function countIncoming(int $targetUserId): int;
    public function paginateIncoming(int $targetUserId, int $page, int $perPage): FollowRequestPage;
    public function accept(int $requesterUserId, int $targetUserId): bool;
    public function reject(int $requesterUserId, int $targetUserId): bool;
    public function acceptAllForTarget(int $targetUserId): void;
}
