<?php

declare(strict_types=1);

namespace App\Preferences;

interface UserMediaPreferenceStore
{
    public function findForUser(int $userId, string $source, string $mediaType, int $sourceId): ?UserMediaPreference;

    public function create(int $userId, UserMediaPreferenceDraft $draft): bool;

    public function update(int $userId, string $source, string $mediaType, int $sourceId, bool $favorite, ?int $rating): bool;

    public function delete(int $userId, string $source, string $mediaType, int $sourceId): bool;

    public function paginateFavorites(int $userId, ?string $mediaType, bool $animeOnly, int $page, int $perPage): UserMediaPreferencePage;

    public function paginateRated(int $userId, ?string $mediaType, bool $animeOnly, int $page, int $perPage): UserMediaPreferencePage;

    public function countFavorites(int $userId): int;

    public function countRated(int $userId): int;
}
