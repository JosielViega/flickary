<?php

declare(strict_types=1);

namespace App\Schedule;

interface ScheduleStore
{
    public function findForUserIdentity(int $userId, string $source, string $entryType, int $sourceId, int $seasonNumber = 0, int $episodeNumber = 0): ?ScheduleEntry;
    public function findForUser(int $userId, int $id): ?ScheduleEntry;
    public function create(int $userId, ScheduleItem $item): bool;
    public function updateDate(int $userId, int $id, string $scheduledOn): bool;
    public function delete(int $userId, int $id): bool;
    public function paginateForUser(int $userId, ?string $entryType, string $today, int $page, int $perPage): SchedulePage;
    public function countForUser(int $userId): int;
    /** @return array<int, ScheduleEntry> */
    public function episodeSchedulesForSeason(int $userId, string $source, int $seriesId, int $seasonNumber): array;
}
