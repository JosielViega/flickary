<?php

declare(strict_types=1);

namespace App\History;

interface WatchHistoryStore
{
    public function createMovie(int $userId, WatchHistoryEvent $event): bool;

    public function createEpisode(int $userId, WatchHistoryEvent $event): bool;

    public function findForUser(int $userId, int $id): ?WatchHistoryEntry;

    public function updateDate(int $userId, int $id, string $watchedOn): bool;

    public function delete(int $userId, int $id): bool;

    public function paginateForUser(
        int $userId,
        ?string $entryType,
        int $page,
        int $perPage,
    ): WatchHistoryPage;

    public function countForUser(int $userId): int;
}
