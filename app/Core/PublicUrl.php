<?php

declare(strict_types=1);

namespace App\Core;

final readonly class PublicUrl
{
    private string $baseUrl;

    public function __construct(string $baseUrl)
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        $parts = parse_url($baseUrl);

        if (
            $baseUrl === ''
            || filter_var($baseUrl, FILTER_VALIDATE_URL) === false
            || !is_array($parts)
            || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || !isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new \InvalidArgumentException('APP_URL must be a valid HTTP or HTTPS base URL.');
        }

        $this->baseUrl = $baseUrl;
    }

    public function forPath(string $path): string
    {
        if (
            $path === ''
            || $path[0] !== '/'
            || str_starts_with($path, '//')
            || str_contains($path, '\\')
            || str_contains($path, '?')
            || str_contains($path, '#')
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
        ) {
            throw new \InvalidArgumentException('Public paths must be absolute internal paths without query or fragment.');
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('Public paths cannot contain dot segments.');
            }
        }

        return $this->baseUrl . $path;
    }
}
