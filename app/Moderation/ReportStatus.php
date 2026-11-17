<?php

declare(strict_types=1);

namespace App\Moderation;

enum ReportStatus: string
{
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Em análise',
            self::Resolved => 'Resolvida',
            self::Dismissed => 'Arquivada',
        };
    }
}
