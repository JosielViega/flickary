<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb;

final class TmdbMediaDetailsNormalizer
{
    public function movie(array $payload): ?TmdbMediaDetails
    {
        return $this->normalize($payload, 'movie', 'title', 'original_title', 'release_date');
    }

    public function series(array $payload): ?TmdbMediaDetails
    {
        return $this->normalize($payload, 'series', 'name', 'original_name', 'first_air_date');
    }

    private function normalize(
        array $payload,
        string $mediaType,
        string $titleKey,
        string $originalTitleKey,
        string $dateKey,
    ): ?TmdbMediaDetails {
        $id = $payload['id'] ?? null;
        if (!is_int($id) || $id < 1 || $id > 2147483647) {
            return null;
        }

        $title = $this->text($payload[$titleKey] ?? null);
        $originalTitle = $this->text($payload[$originalTitleKey] ?? null);
        $title ??= $originalTitle;
        if ($title === null) {
            return null;
        }

        $releaseDate = $this->date($payload[$dateKey] ?? null);

        return new TmdbMediaDetails(
            'tmdb',
            $mediaType,
            $id,
            $title,
            $originalTitle,
            $this->text($payload['tagline'] ?? null),
            $this->text($payload['overview'] ?? null),
            $releaseDate,
            $releaseDate === null ? null : (int) substr($releaseDate, 0, 4),
            $this->filePath($payload['poster_path'] ?? null),
            $this->filePath($payload['backdrop_path'] ?? null),
            $this->genres($payload['genres'] ?? null),
            $this->float($payload['vote_average'] ?? null),
            $this->integer($payload['vote_count'] ?? null, 0),
            $this->float($payload['popularity'] ?? null),
            $this->language($payload['original_language'] ?? null),
            is_bool($payload['adult'] ?? null) ? $payload['adult'] : null,
            $this->text($payload['status'] ?? null),
            $mediaType === 'movie' ? $this->integer($payload['runtime'] ?? null, 1) : null,
            $mediaType === 'series' ? $this->integer($payload['number_of_seasons'] ?? null, 1) : null,
            $mediaType === 'series' ? $this->integer($payload['number_of_episodes'] ?? null, 1) : null,
            $mediaType === 'series' ? $this->date($payload['last_air_date'] ?? null) : null,
            $mediaType === 'series' && is_bool($payload['in_production'] ?? null)
                ? $payload['in_production']
                : null,
            $mediaType === 'series' ? $this->seasons($payload['seasons'] ?? null) : [],
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

    /** @return list<array{id:int,name:string}> */
    private function genres(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $genres = [];
        $seen = [];
        foreach ($values as $value) {
            if (!is_array($value)) {
                continue;
            }
            $id = $value['id'] ?? null;
            $name = $this->text($value['name'] ?? null);
            if (!is_int($id) || $id < 1 || $name === null || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $genres[] = ['id' => $id, 'name' => $name];
        }
        return $genres;
    }

    private function language(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[a-z]{2}$/', $value) === 1 ? $value : null;
    }

    /** @return list<TmdbSeasonSummary> */
    private function seasons(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $seasons = [];

        foreach ($values as $value) {
            if (!is_array($value)) {
                continue;
            }

            $seasonNumber = $this->integer($value['season_number'] ?? null, 0);
            $episodeCount = $this->integer($value['episode_count'] ?? null, 0);
            $name = $this->text($value['name'] ?? null);
            $id = $value['id'] ?? null;

            if (
                $seasonNumber === null
                || $episodeCount === null
                || $name === null
                || ($id !== null && (!is_int($id) || $id < 1))
            ) {
                continue;
            }

            $seasons[] = new TmdbSeasonSummary(
                $id,
                $seasonNumber,
                $name,
                $episodeCount,
                $this->date($value['air_date'] ?? null),
                $this->filePath($value['poster_path'] ?? null),
            );
        }

        usort(
            $seasons,
            static fn (TmdbSeasonSummary $left, TmdbSeasonSummary $right): int =>
                ($left->seasonNumber === 0 ? PHP_INT_MAX : $left->seasonNumber)
                <=> ($right->seasonNumber === 0 ? PHP_INT_MAX : $right->seasonNumber),
        );

        return $seasons;
    }
}
