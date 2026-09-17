<?php

declare(strict_types=1);

namespace App\Authentication;

use App\Core\Session;
use Closure;

final class FacebookOAuthState
{
    private const SESSION_KEY = '_facebook_oauth_state';
    private const TTL_SECONDS = 600;

    private readonly Closure $clock;

    public function __construct(private readonly Session $session, ?Closure $clock = null)
    {
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function issue(string $intent, ?int $userId = null): string
    {
        if (!in_array($intent, ['login', 'link'], true) || ($intent === 'link' && ($userId ?? 0) <= 0)) {
            throw new \InvalidArgumentException('Invalid Facebook OAuth intent.');
        }

        $token = bin2hex(random_bytes(32));
        $this->session->put(self::SESSION_KEY, [
            'token_hash' => hash('sha256', $token),
            'intent' => $intent,
            'user_id' => $userId,
            'created_at' => ($this->clock)(),
        ]);

        return $token;
    }

    public function consume(mixed $token): ?array
    {
        $state = $this->session->get(self::SESSION_KEY);
        $this->session->forget(self::SESSION_KEY);

        if (!is_string($token) || strlen($token) !== 64 || !is_array($state)) {
            return null;
        }

        $createdAt = $state['created_at'] ?? null;
        $intent = $state['intent'] ?? null;
        $userId = $state['user_id'] ?? null;
        if (!is_int($createdAt) || $createdAt > ($this->clock)()
            || ($this->clock)() - $createdAt > self::TTL_SECONDS
            || !in_array($intent, ['login', 'link'], true)
            || ($intent === 'link' && (!is_int($userId) || $userId <= 0))
            || !is_string($state['token_hash'] ?? null)
            || !hash_equals($state['token_hash'], hash('sha256', $token))) {
            return null;
        }

        return ['intent' => $intent, 'user_id' => $userId];
    }
}
