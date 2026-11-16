<?php

declare(strict_types=1);

namespace App\Profiles;

interface ProfileUpdater
{
    public function update(
        int $userId,
        string $displayName,
        ?string $bio,
        bool $wasPrivate,
        bool $isPrivate,
    ): void;
}
