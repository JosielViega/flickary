<?php

declare(strict_types=1);

namespace App\Moderation;

interface ReportStore
{
    public function reportableComment(int $viewerUserId, int $commentId): ?ReportTarget;
    public function reportableMessage(int $viewerUserId, int $messageId): ?ReportTarget;
    public function create(ReportSubmission $submission): ReportCreateResult;
}
