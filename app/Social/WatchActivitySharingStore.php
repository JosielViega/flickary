<?php

declare(strict_types=1);

namespace App\Social;

interface WatchActivitySharingStore
{
    public function setEnabled(int $userId, bool $enabled): void;
}
