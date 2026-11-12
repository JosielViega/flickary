<?php

declare(strict_types=1);

namespace App\Social;

final readonly class PublicProfile
{
    public function __construct(
        public int $userId,
        public string $username,
        public string $displayName,
        public ?string $bio,
        public ?string $avatarUrl,
        public ?string $coverUrl,
        public bool $isPrivate,
        public string $createdAt,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['user_id'],
            (string) $row['username'],
            (string) $row['display_name'],
            is_string($row['bio'] ?? null) ? $row['bio'] : null,
            self::safeHttpsUrl($row['avatar_url'] ?? null),
            self::safeHttpsUrl($row['cover_url'] ?? null),
            (bool) (int) $row['is_private'],
            (string) $row['created_at'],
        );
    }

    private static function safeHttpsUrl(mixed $value): ?string
    {
        if (!is_string($value) || strlen($value) > 2048 || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return strtolower((string) parse_url($value, PHP_URL_SCHEME)) === 'https' ? $value : null;
    }
}
