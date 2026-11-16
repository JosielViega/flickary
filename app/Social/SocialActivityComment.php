<?php

declare(strict_types=1);

namespace App\Social;

final readonly class SocialActivityComment
{
    public function __construct(
        public int $id,
        public int $historyId,
        public int $authorUserId,
        public string $authorUsername,
        public string $authorDisplayName,
        public ?string $authorAvatarUrl,
        public string $body,
        public string $createdAt,
    ) {
    }
}
