<?php

declare(strict_types=1);

namespace App\Discovery;

final readonly class DiscoveryPage
{
    /** @param list<DiscoveryShelf> $shelves @param array<string,string> $posterUrls */
    public function __construct(
        public string $filter,
        public int $year,
        public array $shelves,
        public array $posterUrls = [],
    ) {}

    public function available(): bool
    {
        foreach ($this->shelves as $shelf) {
            if ($shelf->items !== []) {
                return true;
            }
        }
        return false;
    }

    public function posterUrl(string $mediaType, int $sourceId): ?string
    {
        return $this->posterUrls[$mediaType . ':' . $sourceId] ?? null;
    }
}
