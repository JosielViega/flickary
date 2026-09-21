<?php

declare(strict_types=1);

namespace App\Schedule;

final readonly class ScheduleItem
{
    public function __construct(
        public string $source,
        public string $entryType,
        public int $sourceId,
        public int $seasonNumber,
        public int $episodeNumber,
        public string $title,
        public ?string $originalTitle,
        public ?string $episodeTitle,
        public ?string $contentDate,
        public ?string $posterPath,
        public string $scheduledOn,
    ) {
        if (!in_array($entryType, ['movie', 'series', 'episode'], true)
            || $sourceId < 1 || trim($title) === ''
            || ScheduleDate::parse($scheduledOn) === null
            || ($entryType === 'episode' && ($seasonNumber < 0 || $episodeNumber < 1 || trim((string) $episodeTitle) === ''))
            || ($entryType !== 'episode' && ($seasonNumber !== 0 || $episodeNumber !== 0))
        ) {
            throw new \InvalidArgumentException('Invalid schedule item.');
        }
    }
}
