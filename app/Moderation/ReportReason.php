<?php

declare(strict_types=1);

namespace App\Moderation;

enum ReportReason: string
{
    case Spam = 'spam';
    case Harassment = 'harassment';
    case Inappropriate = 'inappropriate';
    case Impersonation = 'impersonation';
    case Privacy = 'privacy';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spam => 'Spam',
            self::Harassment => 'Assédio',
            self::Inappropriate => 'Conteúdo impróprio',
            self::Impersonation => 'Falsidade de identidade',
            self::Privacy => 'Privacidade',
            self::Other => 'Outro',
        };
    }
}
