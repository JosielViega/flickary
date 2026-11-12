<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Anime\AnimeSnapshotBackfillStore;
use App\Core\Database;
use PDO;

final class AnimeSnapshotRepository implements AnimeSnapshotBackfillStore
{
    public function __construct(private readonly Database $database)
    {
    }

    public function countPendingTmdb(): int
    {
        $sql = "SELECT SUM(total) FROM ("
            . "SELECT COUNT(*) AS total FROM user_media WHERE source='tmdb' AND is_anime IS NULL UNION ALL "
            . "SELECT COUNT(*) FROM watch_history WHERE source='tmdb' AND is_anime IS NULL UNION ALL "
            . "SELECT COUNT(*) FROM user_schedule WHERE source='tmdb' AND is_anime IS NULL"
            . ') pending';
        return (int) $this->database->connection()->query($sql)->fetchColumn();
    }

    public function pendingMovieIds(): array
    {
        return $this->identities(
            "SELECT source_id FROM user_media WHERE source='tmdb' AND media_type='movie' AND is_anime IS NULL UNION "
            . "SELECT source_id FROM watch_history WHERE source='tmdb' AND entry_type='movie' AND is_anime IS NULL UNION "
            . "SELECT source_id FROM user_schedule WHERE source='tmdb' AND entry_type='movie' AND is_anime IS NULL",
        );
    }

    public function pendingSeriesIds(): array
    {
        return $this->identities(
            "SELECT source_id FROM user_media WHERE source='tmdb' AND media_type='series' AND is_anime IS NULL UNION "
            . "SELECT source_id FROM watch_history WHERE source='tmdb' AND entry_type='episode' AND is_anime IS NULL UNION "
            . "SELECT source_id FROM user_schedule WHERE source='tmdb' AND entry_type IN ('series','episode') AND is_anime IS NULL",
        );
    }

    public function fillMovie(int $sourceId, bool $isAnime): int
    {
        return $this->fill([
            ['user_media', "media_type='movie'"],
            ['watch_history', "entry_type='movie'"],
            ['user_schedule', "entry_type='movie'"],
        ], $sourceId, $isAnime);
    }

    public function fillSeries(int $sourceId, bool $isAnime): int
    {
        return $this->fill([
            ['user_media', "media_type='series'"],
            ['watch_history', "entry_type='episode'"],
            ['user_schedule', "entry_type IN ('series','episode')"],
        ], $sourceId, $isAnime);
    }

    /** @return list<int> */
    private function identities(string $sql): array
    {
        $rows = $this->database->connection()->query($sql . ' ORDER BY source_id')->fetchAll(PDO::FETCH_COLUMN);
        return array_map('intval', $rows);
    }

    /** @param list<array{string,string}> $targets */
    private function fill(array $targets, int $sourceId, bool $isAnime): int
    {
        $updated = 0;
        foreach ($targets as [$table, $typeFilter]) {
            $statement = $this->database->connection()->prepare(
                "UPDATE {$table} SET is_anime=:is_anime WHERE source='tmdb' AND {$typeFilter} "
                . 'AND source_id=:source_id AND is_anime IS NULL',
            );
            $statement->execute(['is_anime'=>(int)$isAnime, 'source_id'=>$sourceId]);
            $updated += $statement->rowCount();
        }
        return $updated;
    }
}
