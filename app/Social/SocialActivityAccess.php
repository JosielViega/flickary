<?php

declare(strict_types=1);

namespace App\Social;

enum SocialActivityAccess: string
{
    case Owner = 'owner';
    case Follower = 'follower';
}
