<?php

declare(strict_types=1);

namespace App\Notifications;

interface SocialNotificationReader
{
    public function paginateForUser(int $userId, int $page, int $perPage): SocialNotificationPage;
    public function countUnread(int $userId): int;
    public function findForRecipient(int $recipientUserId, int $notificationId): ?SocialNotification;
}
