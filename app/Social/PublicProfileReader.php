<?php

declare(strict_types=1);

namespace App\Social;

interface PublicProfileReader
{
    public function findByUsername(string $username): ?PublicProfile;

    public function statisticsForUser(int $userId): PublicProfileStatistics;

    /** @return list<PublicFavorite> */
    public function recentFavorites(int $userId, int $limit = 6): array;

    public function search(string $query, int $page, int $perPage): PublicProfilePage;
}
