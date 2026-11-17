<?php

declare(strict_types=1);

namespace App\Administration;

interface AdminAccess
{
    public function isAdmin(int $userId): bool;
}
