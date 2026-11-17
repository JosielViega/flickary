<?php

declare(strict_types=1);

namespace App\Messaging;

final readonly class DirectMessagePage
{
    /** @param list<DirectMessage> $items */
    public function __construct(public array $items, public int $total, public int $page, public int $perPage) {}
    public function totalPages(): int { return max(1, (int) ceil($this->total / $this->perPage)); }
}
