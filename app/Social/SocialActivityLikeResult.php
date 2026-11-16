<?php

declare(strict_types=1);

namespace App\Social;

enum SocialActivityLikeResult
{
    case Liked;
    case AlreadyLiked;
    case Hidden;
}
