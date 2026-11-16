<?php

declare(strict_types=1);

namespace App\Core;

final readonly class PageMeta
{
    private const DESCRIPTION_LIMIT = 200;

    public string $description;
    public string $imageAlt;

    public function __construct(
        public string $title,
        string $description,
        public string $canonicalUrl,
        public string $imageUrl,
        string $imageAlt,
        public string $type = 'website',
    ) {
        self::assertWebUrl($canonicalUrl, 'canonicalUrl');
        self::assertWebUrl($imageUrl, 'imageUrl');

        $this->description = self::normalizeText($description, self::DESCRIPTION_LIMIT);
        $this->imageAlt = self::normalizeText($imageAlt, self::DESCRIPTION_LIMIT);
    }

    public static function normalizeDescription(string $description): string
    {
        return self::normalizeText($description, self::DESCRIPTION_LIMIT);
    }

    private static function normalizeText(string $value, int $limit): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', strip_tags($value)));
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $limit - 1)) . '…';
    }

    private static function assertWebUrl(string $url, string $field): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (filter_var($url, FILTER_VALIDATE_URL) === false || !in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException($field . ' must be a valid HTTP or HTTPS URL.');
        }
    }
}
