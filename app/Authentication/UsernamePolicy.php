<?php

declare(strict_types=1);

namespace App\Authentication;

final class UsernamePolicy
{
    public function normalize(mixed $value): ?string
    {
        return is_string($value) ? strtolower($value) : null;
    }

    public function error(?string $username): ?string
    {
        if ($username === null || $username === '') {
            return 'Informe um username.';
        }

        if (preg_match('/^[a-z0-9](?:[a-z0-9_.]{1,28}[a-z0-9])$/', $username) !== 1) {
            return 'Use de 3 a 30 caracteres: letras minúsculas, números, ponto ou underscore, começando e terminando com letra ou número.';
        }

        return null;
    }
}
