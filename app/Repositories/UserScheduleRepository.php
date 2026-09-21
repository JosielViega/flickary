<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Schedule\ScheduleDate;
use App\Schedule\ScheduleEntry;
use App\Schedule\ScheduleItem;
use App\Schedule\SchedulePage;
use App\Schedule\ScheduleStore;
use PDO;
use PDOException;

final class UserScheduleRepository implements ScheduleStore
{
    public function __construct(private readonly Database $database)
    {
    }

    public function findForUserIdentity(int $userId, string $source, string $entryType, int $sourceId, int $seasonNumber = 0, int $episodeNumber = 0): ?ScheduleEntry
    {
        $statement = $this->database->connection()->prepare(
            'SELECT * FROM user_schedule WHERE user_id = :user_id AND source = :source AND entry_type = :entry_type '
            . 'AND source_id = :source_id AND season_number = :season_number AND episode_number = :episode_number LIMIT 1',
        );
        $statement->execute([
            'user_id' => $userId, 'source' => $source, 'entry_type' => $entryType, 'source_id' => $sourceId,
            'season_number' => $seasonNumber, 'episode_number' => $episodeNumber,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->map($row) : null;
    }

    public function findForUser(int $userId, int $id): ?ScheduleEntry
    {
        $statement = $this->database->connection()->prepare('SELECT * FROM user_schedule WHERE id = :id AND user_id = :user_id LIMIT 1');
        $statement->execute(['id' => $id, 'user_id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->map($row) : null;
    }

    public function create(int $userId, ScheduleItem $item): bool
    {
        $sql = 'INSERT INTO user_schedule (user_id, source, entry_type, source_id, season_number, episode_number, title, original_title, episode_title, content_date, poster_path, scheduled_on) '
            . 'VALUES (:user_id,:source,:entry_type,:source_id,:season_number,:episode_number,:title,:original_title,:episode_title,:content_date,:poster_path,:scheduled_on)';
        try {
            $this->database->connection()->prepare($sql)->execute([
                'user_id' => $userId, 'source' => $item->source, 'entry_type' => $item->entryType,
                'source_id' => $item->sourceId, 'season_number' => $item->seasonNumber, 'episode_number' => $item->episodeNumber,
                'title' => mb_substr($item->title, 0, 255),
                'original_title' => $item->originalTitle === null ? null : mb_substr($item->originalTitle, 0, 255),
                'episode_title' => $item->episodeTitle === null ? null : mb_substr($item->episodeTitle, 0, 255),
                'content_date' => $item->contentDate, 'poster_path' => $item->posterPath === null ? null : mb_substr($item->posterPath, 0, 255),
                'scheduled_on' => $item->scheduledOn,
            ]);
        } catch (PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                $existing = $this->findForUserIdentity($userId, $item->source, $item->entryType, $item->sourceId, $item->seasonNumber, $item->episodeNumber);
                if ($existing !== null) {
                    $this->updateDate($userId, $existing->id, $item->scheduledOn);
                    return false;
                }
            }
            throw $exception;
        }
        return true;
    }

    public function updateDate(int $userId, int $id, string $scheduledOn): bool
    {
        if (ScheduleDate::parse($scheduledOn) === null) {
            throw new \InvalidArgumentException('Invalid scheduled date.');
        }
        $statement = $this->database->connection()->prepare('UPDATE user_schedule SET scheduled_on = :scheduled_on WHERE id = :id AND user_id = :user_id');
        $statement->execute(['scheduled_on' => $scheduledOn, 'id' => $id, 'user_id' => $userId]);
        return $statement->rowCount() > 0 || $this->findForUser($userId, $id) !== null;
    }

    public function delete(int $userId, int $id): bool
    {
        $statement = $this->database->connection()->prepare('DELETE FROM user_schedule WHERE id = :id AND user_id = :user_id');
        $statement->execute(['id' => $id, 'user_id' => $userId]);
        return $statement->rowCount() > 0;
    }

    public function paginateForUser(int $userId, ?string $entryType, string $today, int $page, int $perPage): SchedulePage
    {
        if ($entryType !== null && !in_array($entryType, ['movie', 'series', 'episode'], true)) {
            throw new \InvalidArgumentException('Invalid schedule type.');
        }
        $where = 'user_id = :user_id' . ($entryType === null ? '' : ' AND entry_type = :entry_type');
        $params = ['user_id' => $userId] + ($entryType === null ? [] : ['entry_type' => $entryType]);
        $count = $this->database->connection()->prepare('SELECT COUNT(*) FROM user_schedule WHERE ' . $where);
        $count->execute($params);
        $query = $this->database->connection()->prepare('SELECT * FROM user_schedule WHERE ' . $where
            . ' ORDER BY (scheduled_on < :today1) ASC, CASE WHEN scheduled_on >= :today2 THEN scheduled_on END ASC, '
            . 'CASE WHEN scheduled_on < :today3 THEN scheduled_on END DESC, id ASC LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) {
            $query->bindValue(':' . $key, $value, $key === 'user_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $query->bindValue(':today1', $today); $query->bindValue(':today2', $today); $query->bindValue(':today3', $today);
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT); $query->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $query->execute();
        $items = array_map(fn (array $row): ScheduleEntry => $this->map($row), $query->fetchAll(PDO::FETCH_ASSOC));
        return new SchedulePage($items, (int) $count->fetchColumn(), $page, $perPage);
    }

    public function countForUser(int $userId): int
    {
        $statement = $this->database->connection()->prepare('SELECT COUNT(*) FROM user_schedule WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
        return (int) $statement->fetchColumn();
    }

    public function episodeSchedulesForSeason(int $userId, string $source, int $seriesId, int $seasonNumber): array
    {
        $statement = $this->database->connection()->prepare('SELECT * FROM user_schedule WHERE user_id=:user_id AND source=:source AND entry_type=\'episode\' AND source_id=:source_id AND season_number=:season_number ORDER BY episode_number');
        $statement->execute(['user_id'=>$userId,'source'=>$source,'source_id'=>$seriesId,'season_number'=>$seasonNumber]);
        $result = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $entry = $this->map($row); $result[$entry->episodeNumber] = $entry;
        }
        return $result;
    }

    /** @param array<string,mixed> $row */
    private function map(array $row): ScheduleEntry
    {
        return new ScheduleEntry((int)$row['id'],(int)$row['user_id'],(string)$row['source'],(string)$row['entry_type'],(int)$row['source_id'],(int)$row['season_number'],(int)$row['episode_number'],(string)$row['title'],is_string($row['original_title'])?$row['original_title']:null,is_string($row['episode_title'])?$row['episode_title']:null,is_string($row['content_date'])?$row['content_date']:null,is_string($row['poster_path'])?$row['poster_path']:null,(string)$row['scheduled_on'],(string)$row['created_at'],(string)$row['updated_at']);
    }
}
