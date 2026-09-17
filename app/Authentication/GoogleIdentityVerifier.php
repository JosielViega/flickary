<?php

declare(strict_types=1);

namespace App\Authentication;

interface GoogleIdentityVerifier
{
    public function verify(string $credential): ?ExternalIdentity;
}
