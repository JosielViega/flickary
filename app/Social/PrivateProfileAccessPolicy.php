<?php

declare(strict_types=1);

namespace App\Social;

final class PrivateProfileAccessPolicy
{
    public function canView(PublicProfile $profile, ?int $viewerUserId, bool $isFollowing): bool
    {
        return !$profile->isPrivate || $viewerUserId === $profile->userId || $isFollowing;
    }
}
