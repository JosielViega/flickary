<?php

declare(strict_types=1);

namespace App\Social;

interface SocialFeedReader
{
    public function paginateForViewer(int $viewerUserId, int $page, int $perPage): SocialFeedPage;
}
