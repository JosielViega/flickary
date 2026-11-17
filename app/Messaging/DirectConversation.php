<?php

declare(strict_types=1);

namespace App\Messaging;

final readonly class DirectConversation
{
    public function __construct(
        public int $id,
        public int $userLowId,
        public int $userHighId,
        public string $createdAt,
        public string $lastMessageAt,
    ) {
    }
}
