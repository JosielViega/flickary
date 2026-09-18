<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb;

final class TmdbSeasonDetailsNormalizer
{
    /** @param array<string, mixed> $payload */
    public function normalize(array $payload, int $seriesId): ?TmdbSeasonDetails
    {
        $seasonNumber = $this->integer($payload['season_number'] ?? null, 0);
        $name = $this->text($payload['name'] ?? null);

        if ($seasonNumber === null || $name === null) {
            return null;
        }

        $episodes = [];
        $episodePayloads = $payload['episodes'] ?? [];

        if (is_array($episodePayloads)) {
            foreach ($episodePayloads as $episodePayload) {
                if (!is_array($episodePayload)) {
                    continue;
                }

                $episodeNumber = $this->integer($episodePayload['episode_number'] ?? null, 1);
                $episodeSeason = $this->integer($episodePayload['season_number'] ?? null, 0);
                $episodeName = $this->text($episodePayload['name'] ?? null);

                if ($episodeNumber === null || $episodeSeason !== $seasonNumber || $episodeName === null) {
                    continue;
                }

                $episodes[] = new TmdbEpisode(
                    $this->integer($episodePayload['id'] ?? null, 1),
                    $episodeSeason,
                    $episodeNumber,
                    $episodeName,
                    $this->text($episodePayload['overview'] ?? null),
                    $this->date($episodePayload['air_date'] ?? null),
                    $this->integer($episodePayload['runtime'] ?? null, 1),
                    $this->filePath($episodePayload['still_path'] ?? null),
                );
            }
        }

        usort(
            $episodes,
            static fn (TmdbEpisode $left, TmdbEpisode $right): int => $left->episodeNumber <=> $right->episodeNumber,
        );

        return new TmdbSeasonDetails(
            'tmdb',
            $seriesId,
            $this->integer($payload['id'] ?? null, 1),
            $seasonNumber,
            $name,
            $this->text($payload['overview'] ?? null),
            $this->date($payload['air_date'] ?? null),
            $this->filePath($payload['poster_path'] ?? null),
            $episodes,
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

    private function integer(mixed $value, int $minimum): ?int
    {
        return is_int($value) && $value >= $minimum && $value <= 2147483647 ? $value : null;
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
}
