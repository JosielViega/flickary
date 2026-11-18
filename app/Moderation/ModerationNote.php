<?php

declare(strict_types=1);

namespace App\Moderation;

final readonly class ModerationNote
{
    private function __construct(public string $value) {}

    /** @return array{0:?self,1:?string} */
    public static function validate(mixed $value): array
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            return [null, 'A nota administrativa deve ser um texto UTF-8 válido.'];
        }
        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $value));
        $length = mb_strlen($normalized);
        if ($length < 10 || $length > 1000) {
            return [null, 'A nota administrativa deve ter entre 10 e 1000 caracteres.'];
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $normalized) === 1) {
            return [null, 'A nota administrativa contém caracteres não permitidos.'];
        }

        return [new self($normalized), null];
    }
}
