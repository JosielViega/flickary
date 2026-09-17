<?php

declare(strict_types=1);

namespace App\Authentication;

final readonly class AccountCreationResult
{
    public const CREATED = 'created';
    public const IDENTITY_EXISTS = 'identity_exists';
    public const USERNAME_TAKEN = 'username_taken';
    public const EMAIL_CONFLICT = 'email_conflict';

    private function __construct(
        public string $status,
        public ?int $userId = null,
    ) {
    }

    public static function created(int $userId): self
    {
        return new self(self::CREATED, $userId);
    }

    public static function identityExists(int $userId): self
    {
        return new self(self::IDENTITY_EXISTS, $userId);
    }

    public static function usernameTaken(): self
    {
        return new self(self::USERNAME_TAKEN);
    }

    public static function emailConflict(): self
    {
        return new self(self::EMAIL_CONFLICT);
    }
}
