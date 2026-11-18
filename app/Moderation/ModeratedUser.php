<?php

declare(strict_types=1);

namespace App\Moderation;

final readonly class ModeratedUser
{
    public function __construct(
        public int $id,
        public string $username,
        public string $displayName,
        public bool $isAdmin,
        public ?string $suspendedUntil,
        public bool $isSuspended,
    ) {}
}
