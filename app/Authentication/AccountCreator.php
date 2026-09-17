<?php

declare(strict_types=1);

namespace App\Authentication;

interface AccountCreator
{
    public function createFromGoogle(GoogleIdentity $identity, string $username): AccountCreationResult;
}
