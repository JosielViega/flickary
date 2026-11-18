<?php

declare(strict_types=1);

namespace App\Moderation;

enum SuspensionDuration: string
{
    case OneDay = '24h';
    case SevenDays = '7d';
    case ThirtyDays = '30d';

    public function minutes(): int
    {
        return match ($this) {
            self::OneDay => 1440,
            self::SevenDays => 10080,
            self::ThirtyDays => 43200,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::OneDay => '24 horas',
            self::SevenDays => '7 dias',
            self::ThirtyDays => '30 dias',
        };
    }
}
