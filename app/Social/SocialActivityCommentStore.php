<?php

declare(strict_types=1);

namespace App\Social;

interface SocialActivityCommentStore
{
    public function createAuthorized(
        int $viewerUserId,
        int $historyId,
        SocialCommentBody $body,
    ): SocialActivityCommentCreateResult;

    public function deleteAuthorized(int $viewerUserId, int $commentId): ?int;
}
