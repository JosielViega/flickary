<?php

declare(strict_types=1);

namespace App\Moderation;

interface AccountAccessReader
{
    public function activeSuspensionUntil(int $userId): ?string;
}
