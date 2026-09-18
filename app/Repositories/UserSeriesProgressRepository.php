<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Media\UserSeriesProgressStore;
use PDO;
use Throwable;

final class UserSeriesProgressRepository implements UserSeriesProgressStore
{
    public function __construct(private readonly Database $database)
    {
    }

    public function watchedEpisodeNumbersForSeason(
        int $userId,
        string $source,
        int $seriesId,
        int $season,
    ): array {
        $statement = $this->database->connection()->prepare(
            'SELECT episode_number
             FROM user_series_episode_progress
             WHERE user_id = :user_id
               AND source = :source
               AND series_source_id = :series_id
               AND season_number = :season
             ORDER BY episode_number',
        );
        $statement->execute([
            'user_id' => $userId,
            'source' => $source,
            'series_id' => $seriesId,
            'season' => $season,
        ]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function countsBySeason(int $userId, string $source, int $seriesId): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT season_number, COUNT(*) AS total
             FROM user_series_episode_progress
             WHERE user_id = :user_id
               AND source = :source
               AND series_source_id = :series_id
             GROUP BY season_number',
        );
        $statement->execute([
            'user_id' => $userId,
            'source' => $source,
            'series_id' => $seriesId,
        ]);

        $counts = [];

        foreach ($statement->fetchAll() as $row) {
            $counts[(int) $row['season_number']] = (int) $row['total'];
        }

        return $counts;
    }

    public function countForSeries(int $userId, string $source, int $seriesId): int
    {
        $statement = $this->database->connection()->prepare(
            'SELECT COUNT(*)
             FROM user_series_episode_progress
             WHERE user_id = :user_id
               AND source = :source
               AND series_source_id = :series_id',
        );
        $statement->execute([
            'user_id' => $userId,
            'source' => $source,
            'series_id' => $seriesId,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function markWatched(
        int $userId,
        string $source,
        int $seriesId,
        int $season,
        int $episode,
    ): void {
        $statement = $this->database->connection()->prepare(
            'INSERT IGNORE INTO user_series_episode_progress
                (user_id, source, series_source_id, season_number, episode_number)
             VALUES (:user_id, :source, :series_id, :season, :episode)',
        );
        $statement->execute([
            'user_id' => $userId,
            'source' => $source,
            'series_id' => $seriesId,
            'season' => $season,
            'episode' => $episode,
        ]);
    }

    public function unmarkWatched(
        int $userId,
        string $source,
        int $seriesId,
        int $season,
        int $episode,
    ): void {
        $statement = $this->database->connection()->prepare(
            'DELETE FROM user_series_episode_progress
             WHERE user_id = :user_id
               AND source = :source
               AND series_source_id = :series_id
               AND season_number = :season
               AND episode_number = :episode',
        );
        $statement->execute([
            'user_id' => $userId,
            'source' => $source,
            'series_id' => $seriesId,
            'season' => $season,
            'episode' => $episode,
        ]);
    }

    public function markSeasonWatched(
        int $userId,
        string $source,
        int $seriesId,
        int $season,
        array $episodes,
    ): void {
        $connection = $this->database->connection();
        $connection->beginTransaction();

        try {
            foreach (array_unique(array_map('intval', $episodes)) as $episode) {
                if ($episode > 0) {
                    $this->markWatched($userId, $source, $seriesId, $season, $episode);
                }
            }

            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    public function clearSeason(int $userId, string $source, int $seriesId, int $season): void
    {
        $statement = $this->database->connection()->prepare(
            'DELETE FROM user_series_episode_progress
             WHERE user_id = :user_id
               AND source = :source
               AND series_source_id = :series_id
               AND season_number = :season',
        );
        $statement->execute([
            'user_id' => $userId,
            'source' => $source,
            'series_id' => $seriesId,
            'season' => $season,
        ]);
    }
}
