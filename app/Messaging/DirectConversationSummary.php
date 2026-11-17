<?php

declare(strict_types=1);

namespace App\Messaging;

final readonly class DirectConversationSummary
{
    public function __construct(
        public int $conversationId,
        public int $otherUserId,
        public string $otherUsername,
        public string $otherDisplayName,
        public ?string $otherAvatarUrl,
        public string $latestMessage,
        public int $latestSenderUserId,
        public string $latestMessageAt,
        public int $unreadCount,
        public bool $canSendNow,
    ) {
    }
}
