<?php

declare(strict_types=1);

namespace App\Media;

use App\Integrations\Tmdb\TmdbMediaDetails;

interface UserMediaStore
{
    public function findForUser(int $userId, string $source, string $mediaType, int $sourceId): ?UserMediaItem;

    public function create(int $userId, TmdbMediaDetails $details, string $status): bool;

    public function updateStatus(int $userId, string $source, string $mediaType, int $sourceId, string $status): bool;

    public function delete(int $userId, string $source, string $mediaType, int $sourceId): bool;

    public function paginateForUser(int $userId, ?string $status, ?string $mediaType, int $page, int $perPage): UserMediaPage;

    public function countForUser(int $userId): int;
}
