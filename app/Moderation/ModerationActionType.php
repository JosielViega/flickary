<?php

declare(strict_types=1);

namespace App\Moderation;

enum ModerationActionType: string
{
    case SuspendUser = 'suspend_user';
    case UnsuspendUser = 'unsuspend_user';
    case RemoveComment = 'remove_comment';

    public function label(): string
    {
        return match ($this) {
            self::SuspendUser => 'Suspensão temporária',
            self::UnsuspendUser => 'Reativação antecipada',
            self::RemoveComment => 'Remoção de comentário',
        };
    }
}
