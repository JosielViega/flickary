<?php

declare(strict_types=1);

namespace App\Media;

final class UserMediaStatus
{
    public const PLANNED = 'planned';
    public const WATCHING = 'watching';
    public const PAUSED = 'paused';
    public const COMPLETED = 'completed';
    public const DROPPED = 'dropped';

    private const LABELS = [
        self::PLANNED => 'Quero assistir',
        self::WATCHING => 'Assistindo',
        self::PAUSED => 'Pausado',
        self::COMPLETED => 'Concluído',
        self::DROPPED => 'Abandonado',
    ];

    /** @return array<string,string> */
    public static function options(): array
    {
        return self::LABELS;
    }

    public static function isValid(mixed $status): bool
    {
        return is_string($status) && array_key_exists($status, self::LABELS);
    }

    public static function label(string $status): string
    {
        if (!self::isValid($status)) {
            throw new \InvalidArgumentException('Invalid user media status.');
        }

        return self::LABELS[$status];
    }
}
