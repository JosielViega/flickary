<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb;

final readonly class TmdbImageConfiguration
{
    /**
     * @param list<string> $posterSizes
     * @param list<string> $backdropSizes
     */
    public function __construct(
        public string $secureBaseUrl,
        public array $posterSizes,
        public array $backdropSizes,
    ) {
        if (!self::validHttpsBase($secureBaseUrl) || $posterSizes === [] || $backdropSizes === []) {
            throw new TmdbException('unexpected_payload');
        }
        self::sizes($posterSizes);
        self::sizes($backdropSizes);
    }

    public static function fromPayload(array $payload): self
    {
        $images = $payload['images'] ?? null;
        if (!is_array($images)) {
            throw new TmdbException('unexpected_payload');
        }

        $base = $images['secure_base_url'] ?? null;
        $posterSizes = self::sizes($images['poster_sizes'] ?? null);
        $backdropSizes = self::sizes($images['backdrop_sizes'] ?? null);
        if (!is_string($base)) {
            throw new TmdbException('unexpected_payload');
        }

        return new self($base, $posterSizes, $backdropSizes);
    }

    private static function validHttpsBase(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_PASS) === null;
    }

    /** @return list<string> */
    private static function sizes(mixed $values): array
    {
        if (!is_array($values)) {
            throw new TmdbException('unexpected_payload');
        }

        $sizes = [];
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^(?:original|[wh]\d{2,5})$/', $value) !== 1) {
                throw new TmdbException('unexpected_payload');
            }
            $sizes[] = $value;
        }

        return array_values(array_unique($sizes));
    }
}
