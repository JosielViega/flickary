<?php

declare(strict_types=1);

namespace App\Authentication;

readonly class ExternalIdentity
{
    public function __construct(
        public string $provider,
        public string $providerUserId,
        public ?string $email,
        public bool $emailVerified,
        public ?string $displayName,
        public ?string $avatarUrl,
    ) {
    }
}
