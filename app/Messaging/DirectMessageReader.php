<?php

declare(strict_types=1);

namespace App\Messaging;

interface DirectMessageReader
{
    public function conversationExists(int $userA, int $userB): bool;
    public function conversationForUsers(int $currentUserId, int $otherUserId): ?DirectConversation;
    public function paginateConversations(int $userId, int $page, int $perPage): DirectConversationPage;
    public function paginateMessages(int $currentUserId, int $otherUserId, int $page, int $perPage): DirectMessagePage;
    public function countUnreadForUser(int $userId): int;
    public function countUnreadForConversation(int $currentUserId, int $otherUserId): int;
}
