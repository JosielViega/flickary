<?php

declare(strict_types=1);

namespace App\Moderation;

enum ReportTargetType: string
{
    case Profile = 'profile';
    case Comment = 'comment';
    case DirectMessage = 'direct_message';

    public function label(): string
    {
        return match ($this) {
            self::Profile => 'Perfil',
            self::Comment => 'Comentário',
            self::DirectMessage => 'Mensagem privada',
        };
    }
}
