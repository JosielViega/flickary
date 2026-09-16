<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private const SESSION_KEY = '_auth_user_id';

    public function __construct(private readonly Session $session)
    {
    }

    public function check(): bool
    {
        return $this->id() !== null;
    }

    public function id(): ?int
    {
        $userId = $this->session->get(self::SESSION_KEY);

        return is_int($userId) && $userId > 0 ? $userId : null;
    }

    public function login(int $userId): void
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Authenticated user ID must be greater than zero.');
        }

        $this->session->regenerate();
        $this->session->put(self::SESSION_KEY, $userId);
    }

    public function logout(): void
    {
        $this->session->forget(self::SESSION_KEY);
        $this->session->regenerate();
    }
}
