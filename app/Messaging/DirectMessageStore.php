<?php

declare(strict_types=1);

namespace App\Messaging;

interface DirectMessageStore
{
    public function send(int $senderUserId, int $otherUserId, DirectMessageBody $body): bool;
    public function markRead(int $currentUserId, int $otherUserId): bool;
}
