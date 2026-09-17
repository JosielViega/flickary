<?php

declare(strict_types=1);

namespace App\Authentication;

interface ExternalIdentityFinder
{
    public function findUserId(string $provider, string $providerUserId): ?int;
}
