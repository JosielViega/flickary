<?php

declare(strict_types=1);

namespace App\Notifications;

interface SocialNotificationStore
{
    public function markRead(int $recipientUserId, int $notificationId): bool;
    public function markAllRead(int $recipientUserId): void;
    public function followRequest(int $recipientUserId, int $actorUserId): void;
    public function removeFollowRequest(int $recipientUserId, int $actorUserId): void;
    public function followAccepted(int $recipientUserId, int $actorUserId): void;
    public function followAcceptedForPendingTarget(int $targetUserId): void;
    public function removeFollowRequestsForTarget(int $targetUserId): void;
    public function newFollower(int $recipientUserId, int $actorUserId): void;
    public function activityLike(int $historyId, int $actorUserId): void;
    public function removeActivityLike(int $historyId, int $actorUserId): void;
    public function activityComment(int $historyId, int $commentId, int $actorUserId): void;
}
