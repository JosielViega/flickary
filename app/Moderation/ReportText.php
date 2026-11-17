<?php

declare(strict_types=1);

namespace App\Moderation;

final class ReportText
{
    /** @return array{0:?string,1:?string} */
    public static function optional(mixed $value, string $label): array
    {
        if ($value === null || $value === '') {
            return [null, null];
        }
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            return [null, $label . ' deve ser um texto UTF-8 válido.'];
        }
        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $value));
        if ($normalized === '') {
            return [null, null];
        }
        if (mb_strlen($normalized) > 1000) {
            return [$normalized, $label . ' deve ter no máximo 1000 caracteres.'];
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $normalized) === 1) {
            return [$normalized, $label . ' contém caracteres não permitidos.'];
        }

        return [$normalized, null];
    }
}
