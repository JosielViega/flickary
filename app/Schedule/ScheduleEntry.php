<?php

declare(strict_types=1);

namespace App\Schedule;

final readonly class ScheduleEntry
{
    public function __construct(
        public int $id,
        public int $userId,
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
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public function year(): ?int
    {
        return $this->contentDate === null ? null : (int) substr($this->contentDate, 0, 4);
    }

    public function state(string $today): string
    {
        return $this->scheduledOn === $today ? 'today' : ($this->scheduledOn > $today ? 'upcoming' : 'overdue');
    }
}
