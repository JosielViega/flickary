<?php

declare(strict_types=1);

namespace App\Moderation;

final readonly class ModerationAction
{
    public function __construct(
        public int $id,
        public ModerationActionType $type,
        public ?int $reportId,
        public ?int $targetUserId,
        public ?int $performedByUserId,
        public ?string $performedByUsername,
        public ?string $targetType,
        public ?int $targetId,
        public ?string $targetUsername,
        public ?string $targetDisplayName,
        public ?string $contentSnapshot,
        public ?int $durationMinutes,
        public ?string $previousSuspendedUntil,
        public ?string $effectiveUntil,
        public string $note,
        public string $createdAt,
    ) {}
}
