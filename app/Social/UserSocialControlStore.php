<?php

declare(strict_types=1);

namespace App\Social;

interface UserSocialControlStore
{
    public function isBlockedEitherDirection(int $userA, int $userB): bool;
    public function isBlockedBy(int $blockerUserId, int $blockedUserId): bool;
    public function isMuted(int $userId, int $targetUserId): bool;
    public function block(int $blockerUserId, int $blockedUserId): void;
    public function unblock(int $blockerUserId, int $blockedUserId): void;
    public function mute(int $userId, int $targetUserId): bool;
    public function unmute(int $userId, int $targetUserId): void;
    public function paginateBlocked(int $userId, int $page, int $perPage): UserSocialControlPage;
    public function paginateMuted(int $userId, int $page, int $perPage): UserSocialControlPage;
}
