<?php

declare(strict_types=1);

namespace App\History;

final readonly class WatchHistoryEvent
{
    private function __construct(
        public string $source,
        public string $entryType,
        public int $sourceId,
        public ?int $seasonNumber,
        public ?int $episodeNumber,
        public string $title,
        public ?string $originalTitle,
        public ?string $episodeTitle,
        public ?string $contentDate,
        public ?string $posterPath,
        public ?int $durationMinutes,
        public string $watchedOn,
        public string $requestKey,
    ) {
    }

    public static function movie(
        int $sourceId,
        string $title,
        ?string $originalTitle,
        ?string $contentDate,
        ?string $posterPath,
        ?int $durationMinutes,
        string $watchedOn,
        string $requestKey,
    ): self {
        self::guardBase($sourceId, $title, $watchedOn, $requestKey);

        return new self(
            'tmdb',
            'movie',
            $sourceId,
            null,
            null,
            $title,
            $originalTitle,
            null,
            self::validOptionalDate($contentDate),
            $posterPath,
            WatchDuration::normalize($durationMinutes),
            $watchedOn,
            $requestKey,
        );
    }

    public static function episode(
        int $seriesSourceId,
        int $seasonNumber,
        int $episodeNumber,
        string $seriesTitle,
        ?string $originalTitle,
        string $episodeTitle,
        ?string $contentDate,
        ?string $posterPath,
        ?int $durationMinutes,
        string $watchedOn,
        string $requestKey,
    ): self {
        self::guardBase($seriesSourceId, $seriesTitle, $watchedOn, $requestKey);

        if ($seasonNumber < 0 || $episodeNumber < 1 || trim($episodeTitle) === '') {
            throw new \InvalidArgumentException('Invalid episode history event.');
        }

        return new self(
            'tmdb',
            'episode',
            $seriesSourceId,
            $seasonNumber,
            $episodeNumber,
            $seriesTitle,
            $originalTitle,
            $episodeTitle,
            self::validOptionalDate($contentDate),
            $posterPath,
            WatchDuration::normalize($durationMinutes),
            $watchedOn,
            $requestKey,
        );
    }

    private static function guardBase(int $sourceId, string $title, string $watchedOn, string $requestKey): void
    {
        if ($sourceId < 1 || trim($title) === ''
            || WatchHistoryDate::parse($watchedOn) === null
            || WatchHistoryRequestKey::parse($requestKey) === null
        ) {
            throw new \InvalidArgumentException('Invalid watch history event.');
        }
    }

    private static function validOptionalDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return $year >= 1000 && checkdate($month, $day, $year) ? $value : null;
    }
}
