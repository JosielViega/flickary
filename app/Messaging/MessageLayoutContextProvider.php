<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Core\Auth;
use App\Core\LayoutContextProvider;

final readonly class MessageLayoutContextProvider implements LayoutContextProvider
{
    public function __construct(private Auth $auth, private DirectMessageReader $messages) {}

    public function context(): array
    {
        $userId = $this->auth->id();
        return ['messageUnreadCount' => $userId === null ? 0 : $this->messages->countUnreadForUser($userId)];
    }
}
