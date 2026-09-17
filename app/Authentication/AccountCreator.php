<?php

declare(strict_types=1);

namespace App\Authentication;

interface AccountCreator
{
    public function createFromExternalIdentity(ExternalIdentity $identity, string $username): AccountCreationResult;
}
