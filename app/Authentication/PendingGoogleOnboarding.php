<?php

declare(strict_types=1);

namespace App\Authentication;

use App\Core\Session;
use Closure;

final class PendingGoogleOnboarding
{
    private const SESSION_KEY = '_pending_google_onboarding';
    private const PROVIDER = 'google';
    private const TTL_SECONDS = 600;

    private readonly Closure $clock;

    public function __construct(
        private readonly Session $session,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function store(GoogleIdentity $identity): void
    {
        $this->session->put(self::SESSION_KEY, [
            'provider' => self::PROVIDER,
            'provider_user_id' => $identity->subject,
            'email' => $identity->email,
            'email_verified' => $identity->emailVerified,
            'display_name' => $identity->displayName,
            'avatar_url' => $identity->avatarUrl,
            'created_at' => ($this->clock)(),
        ]);
    }

    public function current(): ?GoogleIdentity
    {
        $state = $this->session->get(self::SESSION_KEY);
        if (!$this->isValidState($state)) {
            $this->clear();
            return null;
        }

        if (($this->clock)() - $state['created_at'] > self::TTL_SECONDS) {
            $this->clear();
            return null;
        }

        return new GoogleIdentity(
            $state['provider_user_id'],
            $state['email'],
            $state['email_verified'],
            $state['display_name'],
            $state['avatar_url'],
        );
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    private function isValidState(mixed $state): bool
    {
        return is_array($state)
            && ($state['provider'] ?? null) === self::PROVIDER
            && is_string($state['provider_user_id'] ?? null)
            && $state['provider_user_id'] !== ''
            && strlen($state['provider_user_id']) <= 255
            && (is_string($state['email'] ?? null) || ($state['email'] ?? null) === null)
            && is_bool($state['email_verified'] ?? null)
            && (is_string($state['display_name'] ?? null) || ($state['display_name'] ?? null) === null)
            && (is_string($state['avatar_url'] ?? null) || ($state['avatar_url'] ?? null) === null)
            && is_int($state['created_at'] ?? null)
            && $state['created_at'] <= ($this->clock)();
    }
}
