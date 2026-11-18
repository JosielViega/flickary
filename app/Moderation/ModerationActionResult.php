<?php

declare(strict_types=1);

namespace App\Moderation;

enum ModerationActionResult
{
    case Applied;
    case NotFound;
    case ProtectedTarget;
}
