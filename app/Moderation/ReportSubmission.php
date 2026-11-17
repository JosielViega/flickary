<?php

declare(strict_types=1);

namespace App\Moderation;

final readonly class ReportSubmission
{
    public function __construct(
        public int $reporterUserId,
        public ReportTarget $target,
        public ReportReason $reason,
        public ?string $details,
    ) {
    }
}
