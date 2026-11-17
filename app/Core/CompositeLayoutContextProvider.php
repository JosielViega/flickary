<?php

declare(strict_types=1);

namespace App\Core;

final readonly class CompositeLayoutContextProvider implements LayoutContextProvider
{
    /** @param list<LayoutContextProvider> $providers */
    public function __construct(private array $providers) {}

    public function context(): array
    {
        $context = [];
        foreach ($this->providers as $provider) {
            $context = [...$context, ...$provider->context()];
        }
        return $context;
    }
}
