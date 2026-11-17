<?php

declare(strict_types=1);

namespace App\Notifications;

enum SocialNotificationType: string
{
    case FollowRequest = 'follow_request';
    case NewFollower = 'new_follower';
    case FollowAccepted = 'follow_accepted';
    case ActivityLike = 'activity_like';
    case ActivityComment = 'activity_comment';
}
