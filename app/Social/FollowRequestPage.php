<?php

declare(strict_types=1);

namespace App\Social;

final readonly class FollowRequestPage
{
    /** @param list<FollowRequest> $items */
    public function __construct(public array $items, public int $total, public int $page, public int $perPage)
    {
    }

    public function totalPages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }
}
