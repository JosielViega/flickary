<?php

declare(strict_types=1);

namespace App\Moderation;

interface ReportReader
{
    public function paginateOwn(int $reporterUserId, int $page, int $perPage): SocialReportPage;
}
