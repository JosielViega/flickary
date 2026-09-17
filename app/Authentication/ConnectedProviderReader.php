<?php

declare(strict_types=1);

namespace App\Authentication;

interface ConnectedProviderReader
{
    public function providersForUser(int $userId): array;
}
