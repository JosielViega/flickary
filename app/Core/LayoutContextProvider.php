<?php

declare(strict_types=1);

namespace App\Core;

interface LayoutContextProvider
{
    /** @return array<string,mixed> */
    public function context(): array;
}
