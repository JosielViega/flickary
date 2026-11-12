<?php

declare(strict_types=1);

namespace App\Social;

interface FollowStore
{
    public function follow(int $followerUserId, int $followedUserId): void;
    public function unfollow(int $followerUserId, int $followedUserId): void;
    public function isFollowing(int $followerUserId, int $followedUserId): bool;
    public function countFollowers(int $userId): int;
    public function countFollowing(int $userId): int;
    public function paginateFollowers(int $userId, int $page, int $perPage): PublicProfilePage;
    public function paginateFollowing(int $userId, int $page, int $perPage): PublicProfilePage;
}
