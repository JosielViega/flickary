<?php

declare(strict_types=1);

namespace App\Social;

interface SocialActivityLikeStore
{
    public function likeVisibleActivity(int $viewerUserId, int $historyId): SocialActivityLikeResult;

    public function unlike(int $viewerUserId, int $historyId): void;
}
