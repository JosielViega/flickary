<?php

declare(strict_types=1);

namespace App\Authentication;

use Closure;
use Google\Client;

final class GoogleApiIdentityVerifier implements GoogleIdentityVerifier
{
    private readonly Closure $tokenVerifier;

    public function __construct(
        private readonly string $clientId,
        ?Closure $tokenVerifier = null,
    ) {
        $clientId = $this->clientId;
        $this->tokenVerifier = $tokenVerifier
            ?? static fn (string $credential): array|false => (new Client(['client_id' => $clientId]))
                ->verifyIdToken($credential);
    }

    public function verify(string $credential): ?ExternalIdentity
    {
        if ($this->clientId === '' || trim($credential) === '') {
            return null;
        }

        try {
            $payload = ($this->tokenVerifier)($credential);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($payload)) {
            return null;
        }

        $subject = $payload['sub'] ?? null;
        if (!is_string($subject) || trim($subject) === '' || strlen($subject) > 255) {
            return null;
        }

        $emailVerified = ($payload['email_verified'] ?? false) === true;
        $email = $emailVerified ? $this->validEmail($payload['email'] ?? null) : null;

        return new ExternalIdentity(
            'google',
            trim($subject),
            $email,
            $email !== null,
            $this->displayName($payload['name'] ?? null),
            $this->httpsUrl($payload['picture'] ?? null),
        );
    }

    private function validEmail(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $email = strtolower(trim($value));

        return strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            ? $email
            : null;
    }

    private function displayName(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $value));
        if ($name === '') {
            return null;
        }

        return mb_substr($name, 0, 100);
    }

    private function httpsUrl(mixed $value): ?string
    {
        if (!is_string($value) || strlen($value) > 2048 || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return strtolower((string) parse_url($value, PHP_URL_SCHEME)) === 'https' ? $value : null;
    }
}
