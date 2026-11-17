<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Core\Auth;
use App\Core\LayoutContextProvider;

final readonly class NotificationLayoutContextProvider implements LayoutContextProvider
{
    public function __construct(
        private Auth $auth,
        private SocialNotificationReader $notifications,
    ) {
    }

    public function context(): array
    {
        $userId = $this->auth->id();

        return [
            'notificationUnreadCount' => $userId === null
                ? 0
                : $this->notifications->countUnread($userId),
        ];
    }
}
