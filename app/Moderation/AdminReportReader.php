<?php

declare(strict_types=1);

namespace App\Moderation;

interface AdminReportReader
{
    public function paginateAdmin(?ReportStatus $status, int $page, int $perPage): SocialReportPage;
    public function findAdmin(int $reportId): ?SocialReport;
    public function review(int $reportId, int $reviewerUserId, ReportStatus $status, ?string $moderatorNote): bool;
}
