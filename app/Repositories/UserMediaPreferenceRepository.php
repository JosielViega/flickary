<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Preferences\PersonalRating;
use App\Preferences\UserMediaPreference;
use App\Preferences\UserMediaPreferenceDraft;
use App\Preferences\UserMediaPreferencePage;
use App\Preferences\UserMediaPreferenceStore;
use PDO;
use PDOException;

final class UserMediaPreferenceRepository implements UserMediaPreferenceStore
{
    public function __construct(private readonly Database $database)
    {
    }

    public function findForUser(int $userId, string $source, string $mediaType, int $sourceId): ?UserMediaPreference
    {
        $statement = $this->database->connection()->prepare(
            'SELECT * FROM user_media_preferences WHERE user_id=:user_id AND source=:source '
            . 'AND media_type=:media_type AND source_id=:source_id LIMIT 1',
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

    public function create(int $userId, UserMediaPreferenceDraft $draft): bool
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO user_media_preferences '
            . '(user_id,source,media_type,source_id,title,original_title,release_date,poster_path,is_anime,is_favorite,rating) '
            . 'VALUES (:user_id,:source,:media_type,:source_id,:title,:original_title,:release_date,:poster_path,:is_anime,:is_favorite,:rating)',
        );
        try {
            return $statement->execute([
                'user_id' => $userId,
                'source' => $draft->source,
                'media_type' => $draft->mediaType,
                'source_id' => $draft->sourceId,
                'title' => mb_substr($draft->title, 0, 255),
                'original_title' => $draft->originalTitle === null ? null : mb_substr($draft->originalTitle, 0, 255),
                'release_date' => $draft->releaseDate,
                'poster_path' => $draft->posterPath === null ? null : mb_substr($draft->posterPath, 0, 255),
                'is_anime' => $draft->isAnime === null ? null : (int) $draft->isAnime,
                'is_favorite' => (int) $draft->isFavorite,
                'rating' => $draft->rating,
            ]);
        } catch (PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                return false;
            }
            throw $exception;
        }
    }

    public function update(int $userId, string $source, string $mediaType, int $sourceId, bool $favorite, ?int $rating): bool
    {
        PersonalRating::parse($rating);
        $statement = $this->database->connection()->prepare(
            'UPDATE user_media_preferences SET is_favorite=:favorite,rating=:rating '
            . 'WHERE user_id=:user_id AND source=:source AND media_type=:media_type AND source_id=:source_id',
        );
        $statement->execute([
            'favorite' => (int) $favorite,
            'rating' => $rating,
            'user_id' => $userId,
            'source' => $source,
            'media_type' => $mediaType,
            'source_id' => $sourceId,
        ]);
        return $statement->rowCount() > 0;
    }

    public function delete(int $userId, string $source, string $mediaType, int $sourceId): bool
    {
        $statement = $this->database->connection()->prepare(
            'DELETE FROM user_media_preferences WHERE user_id=:user_id AND source=:source '
            . 'AND media_type=:media_type AND source_id=:source_id',
        );
        $statement->execute(['user_id'=>$userId,'source'=>$source,'media_type'=>$mediaType,'source_id'=>$sourceId]);
        return $statement->rowCount() > 0;
    }

    public function paginateFavorites(int $userId, ?string $mediaType, bool $animeOnly, int $page, int $perPage): UserMediaPreferencePage
    {
        return $this->paginate($userId, $mediaType, $animeOnly, $page, $perPage, 'is_favorite = 1');
    }

    public function paginateRated(int $userId, ?string $mediaType, bool $animeOnly, int $page, int $perPage): UserMediaPreferencePage
    {
        return $this->paginate($userId, $mediaType, $animeOnly, $page, $perPage, 'rating IS NOT NULL');
    }

    public function countFavorites(int $userId): int
    {
        return $this->count($userId, 'is_favorite = 1');
    }

    public function countRated(int $userId): int
    {
        return $this->count($userId, 'rating IS NOT NULL');
    }

    private function paginate(int $userId, ?string $mediaType, bool $animeOnly, int $page, int $perPage, string $mode): UserMediaPreferencePage
    {
        if ($mediaType !== null && !in_array($mediaType, ['movie', 'series'], true)) {
            throw new \InvalidArgumentException('Invalid preference media type.');
        }
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $where = ['user_id = :user_id', $mode];
        $params = ['user_id' => $userId];
        if ($mediaType !== null) {
            $where[] = 'media_type = :media_type';
            $params['media_type'] = $mediaType;
        }
        if ($animeOnly) {
            $where[] = 'is_anime = 1';
        }
        $filter = implode(' AND ', $where);
        $count = $this->database->connection()->prepare('SELECT COUNT(*) FROM user_media_preferences WHERE ' . $filter);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $statement = $this->database->connection()->prepare(
            'SELECT * FROM user_media_preferences WHERE ' . $filter
            . ' ORDER BY updated_at DESC, id DESC LIMIT :limit OFFSET :offset',
        );
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();
        $items = array_map(fn (array $row): UserMediaPreference => $this->map($row), $statement->fetchAll(PDO::FETCH_ASSOC));

        return new UserMediaPreferencePage($items, $total, $page, $perPage);
    }

    private function count(int $userId, string $condition): int
    {
        $statement = $this->database->connection()->prepare(
            'SELECT COUNT(*) FROM user_media_preferences WHERE user_id=:user_id AND ' . $condition,
        );
        $statement->execute(['user_id' => $userId]);
        return (int) $statement->fetchColumn();
    }

    /** @param array<string,mixed> $row */
    private function map(array $row): UserMediaPreference
    {
        return new UserMediaPreference(
            (int) $row['id'],
            (int) $row['user_id'],
            (string) $row['source'],
            (string) $row['media_type'],
            (int) $row['source_id'],
            (string) $row['title'],
            is_string($row['original_title']) ? $row['original_title'] : null,
            is_string($row['release_date']) ? $row['release_date'] : null,
            is_string($row['poster_path']) ? $row['poster_path'] : null,
            $row['is_anime'] === null ? null : (bool) (int) $row['is_anime'],
            (bool) (int) $row['is_favorite'],
            $row['rating'] === null ? null : (int) $row['rating'],
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }
}
