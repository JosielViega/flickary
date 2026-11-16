<?php

declare(strict_types=1);

namespace App\Social;

final readonly class SocialActivityDetail
{
    public function __construct(
        public SocialActivity $activity,
        public SocialActivityAccess $access,
        public bool $canComment,
    ) {
    }
}
