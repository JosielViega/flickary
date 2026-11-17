<?php

declare(strict_types=1);

namespace App\Social;

interface ViewerAwareSocialActivityCommentReader
{
    public function paginateVisibleForActivity(int $historyId, int $page, int $perPage, int $viewerUserId, int $ownerUserId): SocialActivityCommentPage;
}
