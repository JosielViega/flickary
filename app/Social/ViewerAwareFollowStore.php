<?php

declare(strict_types=1);

namespace App\Social;

interface ViewerAwareFollowStore
{
    public function paginateFollowersForViewer(int $userId, int $page, int $perPage, ?int $viewerUserId): PublicProfilePage;
    public function paginateFollowingForViewer(int $userId, int $page, int $perPage, ?int $viewerUserId): PublicProfilePage;
}
