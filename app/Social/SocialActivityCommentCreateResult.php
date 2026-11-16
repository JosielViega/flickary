<?php

declare(strict_types=1);

namespace App\Social;

enum SocialActivityCommentCreateResult
{
    case Created;
    case Hidden;
}
