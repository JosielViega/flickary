<?php

declare(strict_types=1);

namespace App\Social;

final class SocialActivityVisibility
{
    public const SQL = 'f.follower_user_id = :viewer_user_id '
        . 'AND p.share_watch_activity = 1 '
        . 'AND p.share_watch_activity_since IS NOT NULL '
        . 'AND wh.created_at >= p.share_watch_activity_since '
        . 'AND wh.created_at >= f.created_at';

    private function __construct()
    {
    }
}
