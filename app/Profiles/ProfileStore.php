<?php

declare(strict_types=1);

namespace App\Profiles;

interface ProfileStore
{
    public function findByUserId(int $userId): ?array;

    public function updateProfile(int $userId, string $displayName, ?string $bio, bool $isPrivate): void;
}
