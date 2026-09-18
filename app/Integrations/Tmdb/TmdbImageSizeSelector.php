<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb;

final class TmdbImageSizeSelector
{
    /** @param list<string> $available */
    public function poster(array $available): ?string
    {
        return $this->firstAvailable($available, ['w500', 'w342', 'w300', 'w185', 'original']);
    }

    /** @param list<string> $available */
    public function backdrop(array $available): ?string
    {
        return $this->firstAvailable($available, ['w1280', 'w780', 'w300', 'original']);
    }

    /** @param list<string> $available @param list<string> $preferred */
    private function firstAvailable(array $available, array $preferred): ?string
    {
        foreach ($preferred as $size) {
            if (in_array($size, $available, true)) {
                return $size;
            }
        }
        return null;
    }
}
