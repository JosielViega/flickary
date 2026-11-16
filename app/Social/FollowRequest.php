<?php

declare(strict_types=1);

namespace App\Social;

final readonly class FollowRequest
{
    public function __construct(public PublicProfile $requester, public string $createdAt)
    {
    }
}
