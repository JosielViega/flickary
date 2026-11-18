<?php

declare(strict_types=1);

namespace App\Moderation;

interface ModerationActionStore
{
    public function suspendFromReport(int $reportId, int $adminUserId, SuspensionDuration $duration, ModerationNote $note): ModerationActionResult;
    public function unsuspend(string $username, int $adminUserId, ModerationNote $note): ModerationActionResult;
    public function removeReportedComment(int $reportId, int $adminUserId, ModerationNote $note): ModerationActionResult;
}
