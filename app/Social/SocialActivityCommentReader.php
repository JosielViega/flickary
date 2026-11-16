<?php

declare(strict_types=1);

namespace App\Social;

interface SocialActivityCommentReader
{
    public function detailForViewer(int $viewerUserId, int $historyId): ?SocialActivityDetail;

    public function paginateForActivity(int $historyId, int $page, int $perPage): SocialActivityCommentPage;

    public function paginateForAuthor(int $userId, int $page, int $perPage): SocialOwnCommentPage;
}
