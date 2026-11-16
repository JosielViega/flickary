<?php

declare(strict_types=1);

namespace App\Social;

enum FollowRelationshipState: string
{
    case None = 'none';
    case Pending = 'pending';
    case Following = 'following';
}
