<?php

declare(strict_types=1);

namespace App\Administration;

interface AdminUserReader extends AdminAccess
{
    public function setAdmin(string $username, bool $isAdmin): bool;

    /** @return list<string> */
    public function adminUsernames(): array;
}
