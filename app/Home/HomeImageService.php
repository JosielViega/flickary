<?php

declare(strict_types=1);

namespace App\Home;

use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageConfiguration;
use App\Integrations\Tmdb\TmdbImageSizeSelector;
use App\Integrations\Tmdb\TmdbImageUrlBuilder;

final class HomeImageService
{
    private bool $loaded = false;
    private ?TmdbImageConfiguration $configuration = null;

    public function __construct(private readonly TmdbCatalog $catalog)
    {
    }

    public function poster(?string $path): ?string
    {
        if ($path === null) { return null; }
        $configuration = $this->configuration();
        if ($configuration === null) {
            return null;
        }
        $size = (new TmdbImageSizeSelector())->compactPoster($configuration->posterSizes);
        return $size === null ? null : (new TmdbImageUrlBuilder($configuration))->posterUrl($path, $size);
    }

    public function backdrop(?string $path): ?string
    {
        if ($path === null) { return null; }
        $configuration = $this->configuration();
        if ($configuration === null) {
            return null;
        }
        $size = (new TmdbImageSizeSelector())->backdrop($configuration->backdropSizes);
        return $size === null ? null : (new TmdbImageUrlBuilder($configuration))->backdropUrl($path, $size);
    }

    private function configuration(): ?TmdbImageConfiguration
    {
        if ($this->loaded) {
            return $this->configuration;
        }
        $this->loaded = true;
        try {
            $this->configuration = $this->catalog->configuration();
        } catch (TmdbException) {
            $this->configuration = null;
        }
        return $this->configuration;
    }
}
