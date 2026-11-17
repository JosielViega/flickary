<?php

declare(strict_types=1);

namespace App\Moderation;

final readonly class SocialReportPage
{
    /** @param list<SocialReport> $items */
    public function __construct(public array $items, public int $total, public int $page, public int $perPage)
    {
    }

    public function totalPages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }
}
