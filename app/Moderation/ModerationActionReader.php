<?php

declare(strict_types=1);

namespace App\Moderation;

interface ModerationActionReader
{
    public function paginateAll(int $page, int $perPage): ModerationActionPage;
    public function paginateForUser(int $userId, int $page, int $perPage): ModerationActionPage;
    public function findUserByUsername(string $username): ?ModeratedUser;
    public function findUserById(int $userId): ?ModeratedUser;
    public function liveCommentMatchesReport(SocialReport $report): bool;
}
