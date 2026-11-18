<?php

declare(strict_types=1);

namespace App\Discovery;

use App\Integrations\Tmdb\TmdbMedia;

final readonly class DiscoveryShelf
{
    /** @param list<TmdbMedia> $items */
    public function __construct(
        public string $key,
        public string $title,
        public ?string $subtitle,
        public array $items,
        public ?string $error = null,
        public ?string $ctaUrl = null,
        public ?string $ctaLabel = null,
    ) {}
}
