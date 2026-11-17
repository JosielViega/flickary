<?php

declare(strict_types=1);

namespace App\Moderation;

final readonly class SocialReport
{
    public function __construct(
        public int $id,
        public int $reporterUserId,
        public string $reporterUsername,
        public ?int $reportedUserId,
        public ReportTargetType $targetType,
        public int $targetId,
        public ReportReason $reason,
        public ?string $details,
        public ?string $targetUsername,
        public ?string $targetDisplayName,
        public ?string $contentSnapshot,
        public ReportStatus $status,
        public ?int $reviewedByUserId,
        public ?string $reviewedAt,
        public ?string $moderatorNote,
        public string $createdAt,
    ) {
    }
}
