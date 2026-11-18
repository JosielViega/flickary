<?php

declare(strict_types=1);

namespace App\Navigation;

use App\Core\Request;

final readonly class PartialNavigationRequest
{
    public function __construct(private Request $request) {}

    public function isPartial(): bool
    {
        return $this->request->method() === 'GET'
            && strcasecmp(trim((string) $this->request->header('X-Flickary-Navigation')), 'partial') === 0;
    }
}
