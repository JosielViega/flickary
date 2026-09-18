<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Integrations\Tmdb\TmdbMediaDetails;
use App\Media\UserMediaItem;
use App\Media\UserMediaPage;
use App\Media\UserMediaStatus;
use App\Media\UserMediaStore;
use PDO;
use PDOException;

final class UserMediaRepository implements UserMediaStore
{
    public function __construct(private readonly Database $database)
    {
    }

    public function findForUser(int $userId, string $source, string $mediaType, int $sourceId): ?UserMediaItem
    {
        $statement = $this->database->connection()->prepare(
            'SELECT * FROM user_media WHERE user_id = :user_id AND source = :source '
            . 'AND media_type = :media_type AND source_id = :source_id LIMIT 1',
        );
        $statement->execute([
            'user_id' => $userId,
            'source' => $source,
            'media_type' => $mediaType,
            'source_id' => $sourceId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->map($row) : null;
    }

    public function create(int $userId, TmdbMediaDetails $details, string $status): bool
    {
        $this->guardStatus($status);
        $statement = $this->database->connection()->prepare(
            'INSERT INTO user_media '
            . '(user_id, source, media_type, source_id, status, title, original_title, release_date, poster_path, backdrop_path) '
            . 'VALUES (:user_id, :source, :media_type, :source_id, :status, :title, :original_title, :release_date, :poster_path, :backdrop_path)',
        );

        try {
            $statement->execute([
                'user_id' => $userId,
                'source' => $details->source,
                'media_type' => $details->mediaType,
                'source_id' => $details->sourceId,
                'status' => $status,
                'title' => mb_substr($details->title, 0, 255),
                'original_title' => $details->originalTitle === null ? null : mb_substr($details->originalTitle, 0, 255),
                'release_date' => $details->releaseDate,
                'poster_path' => $details->posterPath === null ? null : mb_substr($details->posterPath, 0, 255),
                'backdrop_path' => $details->backdropPath === null ? null : mb_substr($details->backdropPath, 0, 255),
            ]);
        } catch (PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                return false;
            }
            throw $exception;
        }

        return true;
    }

    public function updateStatus(int $userId, string $source, string $mediaType, int $sourceId, string $status): bool
    {
        $this->guardStatus($status);
        $statement = $this->database->connection()->prepare(
            'UPDATE user_media SET status = :status WHERE user_id = :user_id AND source = :source '
            . 'AND media_type = :media_type AND source_id = :source_id',
        );
        $statement->execute([
            'status' => $status,
            'user_id' => $userId,
            'source' => $source,
            'media_type' => $mediaType,
            'source_id' => $sourceId,
        ]);

        return $statement->rowCount() > 0 || $this->findForUser($userId, $source, $mediaType, $sourceId) !== null;
    }

    public function delete(int $userId, string $source, string $mediaType, int $sourceId): bool
    {
        $statement = $this->database->connection()->prepare(
            'DELETE FROM user_media WHERE user_id = :user_id AND source = :source '
            . 'AND media_type = :media_type AND source_id = :source_id',
        );
        $statement->execute([
            'user_id' => $userId,
            'source' => $source,
            'media_type' => $mediaType,
            'source_id' => $sourceId,
        ]);

        return $statement->rowCount() > 0;
    }

    public function paginateForUser(int $userId, ?string $status, ?string $mediaType, int $page, int $perPage): UserMediaPage
    {
        $where = ['user_id = :user_id'];
        $params = ['user_id' => $userId];
        if ($status !== null) {
            $this->guardStatus($status);
            $where[] = 'status = :status';
            $params['status'] = $status;
        }
        if ($mediaType !== null) {
            if (!in_array($mediaType, ['movie', 'series'], true)) {
                throw new \InvalidArgumentException('Invalid media type.');
            }
            $where[] = 'media_type = :media_type';
            $params['media_type'] = $mediaType;
        }
        $filter = implode(' AND ', $where);
        $count = $this->database->connection()->prepare('SELECT COUNT(*) FROM user_media WHERE ' . $filter);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $query = $this->database->connection()->prepare(
            'SELECT * FROM user_media WHERE ' . $filter . ' ORDER BY updated_at DESC, id DESC LIMIT :limit OFFSET :offset',
        );
        foreach ($params as $key => $value) {
            $query->bindValue(':' . $key, $value, $key === 'user_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();
        $items = array_map(fn (array $row): UserMediaItem => $this->map($row), $query->fetchAll(PDO::FETCH_ASSOC));

        return new UserMediaPage($items, $total, $page, $perPage);
    }

    public function countForUser(int $userId): int
    {
        $statement = $this->database->connection()->prepare('SELECT COUNT(*) FROM user_media WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
        return (int) $statement->fetchColumn();
    }

    private function guardStatus(string $status): void
    {
        if (!UserMediaStatus::isValid($status)) {
            throw new \InvalidArgumentException('Invalid user media status.');
        }
    }

    /** @param array<string,mixed> $row */
    private function map(array $row): UserMediaItem
    {
        return new UserMediaItem(
            (int) $row['id'],
            (int) $row['user_id'],
            (string) $row['source'],
            (string) $row['media_type'],
            (int) $row['source_id'],
            (string) $row['status'],
            (string) $row['title'],
            is_string($row['original_title']) ? $row['original_title'] : null,
            is_string($row['release_date']) ? $row['release_date'] : null,
            is_string($row['poster_path']) ? $row['poster_path'] : null,
            is_string($row['backdrop_path']) ? $row['backdrop_path'] : null,
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }
}
