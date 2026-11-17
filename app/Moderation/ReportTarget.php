<?php

declare(strict_types=1);

namespace App\Moderation;

final readonly class ReportTarget
{
    public function __construct(
        public ReportTargetType $type,
        public int $id,
        public int $reportedUserId,
        public string $username,
        public string $displayName,
        public ?string $avatarUrl,
        public ?string $contentSnapshot,
    ) {
    }

    public function dedupeKey(): string
    {
        return $this->type->value . ':' . $this->id;
    }
}
