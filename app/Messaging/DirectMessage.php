<?php

declare(strict_types=1);

namespace App\Messaging;

final readonly class DirectMessage
{
    public function __construct(
        public int $id,
        public int $conversationId,
        public int $senderUserId,
        public string $body,
        public ?string $readAt,
        public string $createdAt,
    ) {
    }
}
