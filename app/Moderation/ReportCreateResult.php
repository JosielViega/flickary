<?php

declare(strict_types=1);

namespace App\Moderation;

enum ReportCreateResult
{
    case Created;
    case Duplicate;
    case RateLimited;
}
