<?php

declare(strict_types=1);

namespace App\Authentication;

interface FacebookIdentityProvider
{
    public function configured(): bool;

    public function authorizationUrl(string $state): string;

    public function identityFromCode(string $code): ?ExternalIdentity;
}
