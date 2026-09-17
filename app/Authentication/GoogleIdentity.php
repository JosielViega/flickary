<?php

declare(strict_types=1);

namespace App\Authentication;

final readonly class GoogleIdentity
{
    public function __construct(
        public string $subject,
        public ?string $email,
        public bool $emailVerified,
        public ?string $displayName,
        public ?string $avatarUrl,
    ) {
    }
}
