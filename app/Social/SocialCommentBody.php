<?php

declare(strict_types=1);

namespace App\Social;

final readonly class SocialCommentBody
{
    public const MAX_LENGTH = 500;

    private function __construct(public string $value)
    {
    }

    /** @return array{0: ?self, 1: ?string} */
    public static function validate(mixed $value): array
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            return [null, 'Escreva um comentário válido.'];
        }

        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $value));
        if ($normalized === '') {
            return [null, 'Escreva um comentário antes de enviar.'];
        }
        if (mb_strlen($normalized) > self::MAX_LENGTH) {
            return [null, 'O comentário deve ter no máximo 500 caracteres.'];
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $normalized) === 1) {
            return [null, 'O comentário contém caracteres não permitidos.'];
        }

        return [new self($normalized), null];
    }
}
