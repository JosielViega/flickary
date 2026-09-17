<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb;

final readonly class TmdbImageUrlBuilder
{
    public function __construct(private TmdbImageConfiguration $configuration)
    {
    }

    public function posterUrl(?string $filePath, string $size): ?string
    {
        return $this->build($filePath, $size, $this->configuration->posterSizes);
    }

    public function backdropUrl(?string $filePath, string $size): ?string
    {
        return $this->build($filePath, $size, $this->configuration->backdropSizes);
    }

    /** @param list<string> $allowedSizes */
    private function build(?string $filePath, string $size, array $allowedSizes): ?string
    {
        if (!in_array($size, $allowedSizes, true) || $filePath === null
            || preg_match('#^/[A-Za-z0-9._-]+$#', $filePath) !== 1) {
            return null;
        }

        return rtrim($this->configuration->secureBaseUrl, '/') . '/' . $size . $filePath;
    }
}
