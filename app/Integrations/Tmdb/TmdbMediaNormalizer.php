<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb;

final class TmdbMediaNormalizer
{
    public function movie(array $payload): ?TmdbMedia
    {
        return $this->normalize($payload, 'movie', 'title', 'original_title', 'release_date');
    }

    public function series(array $payload): ?TmdbMedia
    {
        return $this->normalize($payload, 'series', 'name', 'original_name', 'first_air_date');
    }

    private function normalize(array $payload, string $mediaType, string $titleKey, string $originalTitleKey, string $dateKey): ?TmdbMedia
    {
        $id = $payload['id'] ?? null;
        if (!is_int($id) || $id < 1) {
            return null;
        }

        $title = $this->text($payload[$titleKey] ?? null);
        $originalTitle = $this->text($payload[$originalTitleKey] ?? null);
        $title ??= $originalTitle;
        if ($title === null) {
            return null;
        }

        $releaseDate = $this->date($payload[$dateKey] ?? null);

        return new TmdbMedia(
            'tmdb',
            $mediaType,
            $id,
            $title,
            $originalTitle,
            $this->text($payload['overview'] ?? null),
            $releaseDate,
            $releaseDate === null ? null : (int) substr($releaseDate, 0, 4),
            $this->filePath($payload['poster_path'] ?? null),
            $this->filePath($payload['backdrop_path'] ?? null),
            $this->float($payload['vote_average'] ?? null),
            $this->integer($payload['vote_count'] ?? null, 0),
            $this->float($payload['popularity'] ?? null),
            $this->genreIds($payload['genre_ids'] ?? null),
            $this->language($payload['original_language'] ?? null),
            is_bool($payload['adult'] ?? null) ? $payload['adult'] : null,
        );
    }

    private function text(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $value));
        return $value === '' ? null : $value;
    }

    private function date(mixed $value): ?string
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }
        [$year, $month, $day] = array_map('intval', explode('-', $value));
        return $year >= 1 && checkdate($month, $day, $year) ? $value : null;
    }

    private function filePath(mixed $value): ?string
    {
        return is_string($value) && preg_match('#^/[A-Za-z0-9._-]+$#', $value) === 1 ? $value : null;
    }

    private function float(mixed $value): ?float
    {
        return is_int($value) || is_float($value) ? (float) $value : null;
    }

    private function integer(mixed $value, int $minimum): ?int
    {
        return is_int($value) && $value >= $minimum ? $value : null;
    }

    /** @return list<int> */
    private function genreIds(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }
        return array_values(array_unique(array_filter(
            $values,
            static fn (mixed $value): bool => is_int($value) && $value > 0,
        )));
    }

    private function language(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[a-z]{2}$/', $value) === 1 ? $value : null;
    }
}
