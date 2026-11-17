<?php

declare(strict_types=1);

namespace App\Social;

final readonly class UserSocialControl
{
    public function __construct(
        public int $userId,
        public string $username,
        public string $displayName,
        public ?string $avatarUrl,
        public string $createdAt,
    ) {}
}
