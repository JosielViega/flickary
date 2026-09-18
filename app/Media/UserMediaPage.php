<?php

declare(strict_types=1);

namespace App\Media;

final readonly class UserMediaPage
{
    /** @param list<UserMediaItem> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }
}
