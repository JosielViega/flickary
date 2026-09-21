<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\History\WatchHistoryDate;
use App\History\WatchHistoryEntry;
use App\History\WatchHistoryEvent;
use App\History\WatchHistoryPage;
use App\History\WatchHistoryStore;
use App\History\WatchHistoryDurationStore;
use PDO;
use PDOException;

final class WatchHistoryRepository implements WatchHistoryStore, WatchHistoryDurationStore
{
    public function __construct(private readonly Database $database)
    {
    }

    public function createMovie(int $userId, WatchHistoryEvent $event): bool
    {
        if ($event->entryType !== 'movie') {
            throw new \InvalidArgumentException('Expected a movie history event.');
        }

        return $this->create($userId, $event);
    }

    public function createEpisode(int $userId, WatchHistoryEvent $event): bool
    {
        if ($event->entryType !== 'episode') {
            throw new \InvalidArgumentException('Expected an episode history event.');
        }

        return $this->create($userId, $event);
    }

    public function findForUser(int $userId, int $id): ?WatchHistoryEntry
    {
        $statement = $this->database->connection()->prepare(
            'SELECT * FROM watch_history WHERE id = :id AND user_id = :user_id LIMIT 1',
        );
        $statement->execute(['id' => $id, 'user_id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->map($row) : null;
    }

    public function updateDate(int $userId, int $id, string $watchedOn): bool
    {
        if (WatchHistoryDate::parse($watchedOn) === null) {
            throw new \InvalidArgumentException('Invalid watched date.');
        }

        $statement = $this->database->connection()->prepare(
            'UPDATE watch_history SET watched_on = :watched_on WHERE id = :id AND user_id = :user_id',
        );
        $statement->execute([
            'watched_on' => $watchedOn,
            'id' => $id,
            'user_id' => $userId,
        ]);

        return $statement->rowCount() > 0 || $this->findForUser($userId, $id) !== null;
    }

    public function delete(int $userId, int $id): bool
    {
        $statement = $this->database->connection()->prepare(
            'DELETE FROM watch_history WHERE id = :id AND user_id = :user_id',
        );
        $statement->execute(['id' => $id, 'user_id' => $userId]);

        return $statement->rowCount() > 0;
    }

    public function paginateForUser(
        int $userId,
        ?string $entryType,
        int $page,
        int $perPage,
    ): WatchHistoryPage {
        if ($entryType !== null && !in_array($entryType, ['movie', 'episode'], true)) {
            throw new \InvalidArgumentException('Invalid history entry type.');
        }

        if ($page < 1 || $perPage < 1) {
            throw new \InvalidArgumentException('Invalid history pagination.');
        }

        $where = ['user_id = :user_id'];
        $params = ['user_id' => $userId];

        if ($entryType !== null) {
            $where[] = 'entry_type = :entry_type';
            $params['entry_type'] = $entryType;
        }

        $filter = implode(' AND ', $where);
        $count = $this->database->connection()->prepare('SELECT COUNT(*) FROM watch_history WHERE ' . $filter);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $query = $this->database->connection()->prepare(
            'SELECT * FROM watch_history WHERE ' . $filter
            . ' ORDER BY watched_on DESC, id DESC LIMIT :limit OFFSET :offset',
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        if ($entryType !== null) {
            $query->bindValue(':entry_type', $entryType, PDO::PARAM_STR);
        }
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $query->execute();

        $items = array_map(
            fn (array $row): WatchHistoryEntry => $this->map($row),
            $query->fetchAll(PDO::FETCH_ASSOC),
        );

        return new WatchHistoryPage($items, $total, $page, $perPage);
    }

    public function countForUser(int $userId): int
    {
        $statement = $this->database->connection()->prepare(
            'SELECT COUNT(*) FROM watch_history WHERE user_id = :user_id',
        );
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    public function countMissingDuration(): int
    {
        return (int) $this->database->connection()
            ->query('SELECT COUNT(*) FROM watch_history WHERE duration_minutes IS NULL')
            ->fetchColumn();
    }

    public function missingMovieIdentities(): array
    {
        $rows = $this->database->connection()->query(
            "SELECT source, source_id FROM watch_history WHERE entry_type = 'movie' "
            . 'AND duration_minutes IS NULL GROUP BY source, source_id ORDER BY source, source_id',
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => [
            'source' => (string) $row['source'],
            'source_id' => (int) $row['source_id'],
        ], $rows);
    }

    public function missingEpisodeSeasons(): array
    {
        $rows = $this->database->connection()->query(
            "SELECT source, source_id, season_number FROM watch_history WHERE entry_type = 'episode' "
            . 'AND duration_minutes IS NULL GROUP BY source, source_id, season_number '
            . 'ORDER BY source, source_id, season_number',
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => [
            'source' => (string) $row['source'],
            'source_id' => (int) $row['source_id'],
            'season_number' => (int) $row['season_number'],
        ], $rows);
    }

    public function fillMovieDuration(string $source, int $sourceId, int $durationMinutes): int
    {
        return $this->fillDuration(
            "source = :source AND entry_type = 'movie' AND source_id = :source_id",
            ['source' => $source, 'source_id' => $sourceId],
            $durationMinutes,
        );
    }

    public function fillEpisodeDuration(string $source, int $sourceId, int $seasonNumber, int $episodeNumber, int $durationMinutes): int
    {
        return $this->fillDuration(
            "source = :source AND entry_type = 'episode' AND source_id = :source_id "
            . 'AND season_number = :season_number AND episode_number = :episode_number',
            ['source' => $source, 'source_id' => $sourceId, 'season_number' => $seasonNumber, 'episode_number' => $episodeNumber],
            $durationMinutes,
        );
    }

    /** @param array<string, int|string> $identity */
    private function fillDuration(string $identitySql, array $identity, int $durationMinutes): int
    {
        $duration = \App\History\WatchDuration::normalize($durationMinutes);
        if ($duration === null) {
            throw new \InvalidArgumentException('Invalid duration.');
        }
        $statement = $this->database->connection()->prepare(
            'UPDATE watch_history SET duration_minutes = :duration_minutes WHERE duration_minutes IS NULL AND ' . $identitySql,
        );
        $statement->execute(['duration_minutes' => $duration, ...$identity]);
        return $statement->rowCount();
    }

    private function create(int $userId, WatchHistoryEvent $event): bool
    {
        if ($this->requestKeyExists($userId, $event->requestKey)) {
            return false;
        }

        $statement = $this->database->connection()->prepare(
            'INSERT INTO watch_history (
                user_id, source, entry_type, source_id, season_number, episode_number,
                title, original_title, episode_title, content_date, poster_path, duration_minutes,
                watched_on, request_key
             ) VALUES (
                :user_id, :source, :entry_type, :source_id, :season_number, :episode_number,
                :title, :original_title, :episode_title, :content_date, :poster_path, :duration_minutes,
                :watched_on, :request_key
             )',
        );

        try {
            $statement->execute([
                'user_id' => $userId,
                'source' => $event->source,
                'entry_type' => $event->entryType,
                'source_id' => $event->sourceId,
                'season_number' => $event->seasonNumber,
                'episode_number' => $event->episodeNumber,
                'title' => mb_substr($event->title, 0, 255),
                'original_title' => $event->originalTitle === null
                    ? null
                    : mb_substr($event->originalTitle, 0, 255),
                'episode_title' => $event->episodeTitle === null
                    ? null
                    : mb_substr($event->episodeTitle, 0, 255),
                'content_date' => $event->contentDate,
                'poster_path' => $event->posterPath === null
                    ? null
                    : mb_substr($event->posterPath, 0, 255),
                'duration_minutes' => $event->durationMinutes,
                'watched_on' => $event->watchedOn,
                'request_key' => $event->requestKey,
            ]);
        } catch (PDOException $exception) {
            $isDuplicate = (int) ($exception->errorInfo[1] ?? 0) === 1062;
            if ($isDuplicate && $this->requestKeyExists($userId, $event->requestKey)) {
                return false;
            }

            throw $exception;
        }

        return true;
    }

    private function requestKeyExists(int $userId, string $requestKey): bool
    {
        $statement = $this->database->connection()->prepare(
            'SELECT 1 FROM watch_history WHERE user_id = :user_id AND request_key = :request_key LIMIT 1',
        );
        $statement->execute(['user_id' => $userId, 'request_key' => $requestKey]);

        return $statement->fetchColumn() !== false;
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): WatchHistoryEntry
    {
        return new WatchHistoryEntry(
            (int) $row['id'],
            (int) $row['user_id'],
            (string) $row['source'],
            (string) $row['entry_type'],
            (int) $row['source_id'],
            $row['season_number'] === null ? null : (int) $row['season_number'],
            $row['episode_number'] === null ? null : (int) $row['episode_number'],
            (string) $row['title'],
            is_string($row['original_title']) ? $row['original_title'] : null,
            is_string($row['episode_title']) ? $row['episode_title'] : null,
            is_string($row['content_date']) ? $row['content_date'] : null,
            is_string($row['poster_path']) ? $row['poster_path'] : null,
            isset($row['duration_minutes']) ? (int) $row['duration_minutes'] : null,
            (string) $row['watched_on'],
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }
}
