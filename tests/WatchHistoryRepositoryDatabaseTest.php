<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\History\WatchHistoryEvent;
use App\Integrations\Tmdb\TmdbMediaDetails;
use App\Repositories\UserMediaRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSeriesProgressRepository;
use App\Repositories\WatchHistoryRepository;
use PHPUnit\Framework\TestCase;

final class WatchHistoryRepositoryDatabaseTest extends TestCase
{
    private Database $database;
    private WatchHistoryRepository $history;
    private UserRepository $users;

    protected function setUp(): void
    {
        $databaseName = getenv('FLICKARY_TEST_DB_DATABASE');
        if (!is_string($databaseName) || !str_ends_with($databaseName, '_test')) {
            self::markTestSkipped('Disposable Flickary database was not configured.');
        }

        $this->database = new Database([
            'host' => getenv('FLICKARY_TEST_DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('FLICKARY_TEST_DB_PORT') ?: 3306),
            'database' => $databaseName,
            'username' => getenv('FLICKARY_TEST_DB_USERNAME') ?: '',
            'password' => getenv('FLICKARY_TEST_DB_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
        ]);
        $this->history = new WatchHistoryRepository($this->database);
        $this->users = new UserRepository($this->database);
        $this->clear();
    }

    protected function tearDown(): void
    {
        if (isset($this->database)) { $this->clear(); }
    }

    public function testSchemaIndexesForeignKeyAndRequestKeyScope(): void
    {
        $pdo = $this->database->connection();
        $indexes = $pdo->query('SHOW INDEX FROM watch_history')->fetchAll();
        $names = array_unique(array_column($indexes, 'Key_name'));
        self::assertContains('uq_watch_history_request', $names);
        self::assertContains('idx_watch_history_chronology', $names);
        self::assertContains('idx_watch_history_type', $names);
        self::assertContains('idx_watch_history_source', $names);
        $create = (string) $pdo->query('SHOW CREATE TABLE watch_history')->fetchColumn(1);
        self::assertStringContainsString('FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE', $create);
        self::assertStringContainsString('`duration_minutes` smallint(5) unsigned default null', strtolower($create));

        $first = $this->users->create('history.schema.one', null);
        $second = $this->users->create('history.schema.two', null);
        $event = $this->movie(str_repeat('1', 32), '2026-09-10');
        self::assertTrue($this->history->createMovie($first, $event));
        self::assertFalse($this->history->createMovie($first, $event));
        self::assertTrue($this->history->createMovie($second, $event));
    }

    public function testRewatchOrderFiltersPaginationOwnershipUpdateAndDelete(): void
    {
        $owner = $this->users->create('history.owner', null);
        $other = $this->users->create('history.other', null);
        self::assertTrue($this->history->createMovie($owner, $this->movie(str_repeat('a', 32), '2026-09-10')));
        self::assertTrue($this->history->createMovie($owner, $this->movie(str_repeat('b', 32), '2026-09-10')));
        self::assertTrue($this->history->createEpisode($owner, $this->episode(str_repeat('c', 32), '2026-09-11')));

        self::assertSame(3, $this->history->countForUser($owner));
        self::assertSame(136, $this->history->paginateForUser($owner, 'movie', 1, 30)->items[0]->durationMinutes);
        $all = $this->history->paginateForUser($owner, null, 1, 2);
        self::assertSame(3, $all->total);
        self::assertSame('episode', $all->items[0]->entryType);
        self::assertSame(2, $all->lastPage());
        $movies = $this->history->paginateForUser($owner, 'movie', 1, 30);
        self::assertCount(2, $movies->items);
        self::assertGreaterThan($movies->items[1]->id, $movies->items[0]->id);

        $id = $movies->items[0]->id;
        self::assertNull($this->history->findForUser($other, $id));
        self::assertFalse($this->history->updateDate($other, $id, '2020-01-01'));
        self::assertTrue($this->history->updateDate($owner, $id, '2020-01-01'));
        self::assertSame('2020-01-01', $this->history->findForUser($owner, $id)?->watchedOn);
        self::assertSame(136, $this->history->findForUser($owner, $id)?->durationMinutes);
        self::assertFalse($this->history->delete($other, $id));
        self::assertTrue($this->history->delete($owner, $id));
        self::assertSame(2, $this->history->countForUser($owner));
    }

    public function testHistoryIsIndependentAndUserDeletionCascades(): void
    {
        $userId = $this->users->create('history.independent', null);
        $media = new UserMediaRepository($this->database);
        $progress = new UserSeriesProgressRepository($this->database);
        $movie = new TmdbMediaDetails('tmdb', 'movie', 603, 'Matrix', 'The Matrix', null, null, '1999-03-31', 1999, '/matrix.jpg', null, [], null, null, null, 'en', false, null, 136, null, null, null, null);
        $series = new TmdbMediaDetails('tmdb', 'series', 1396, 'Breaking Bad', 'Breaking Bad', null, null, '2008-01-20', 2008, '/bb.jpg', null, [], null, null, null, 'en', false, null, null, 5, 62, null, false);
        $media->create($userId, $movie, 'planned');
        $media->create($userId, $series, 'watching');
        $progress->markWatched($userId, 'tmdb', 1396, 1, 1);
        $this->history->createMovie($userId, $this->movie(str_repeat('d', 32), '2026-09-10'));
        $this->history->createEpisode($userId, $this->episode(str_repeat('e', 32), '2026-09-10'));

        self::assertSame('planned', $media->findForUser($userId, 'tmdb', 'movie', 603)?->status);
        self::assertSame([1], $progress->watchedEpisodeNumbersForSeason($userId, 'tmdb', 1396, 1));
        $media->delete($userId, 'tmdb', 'movie', 603);
        $media->delete($userId, 'tmdb', 'series', 1396);
        $progress->clearSeason($userId, 'tmdb', 1396, 1);
        self::assertSame(2, $this->history->countForUser($userId));

        $this->database->connection()->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
        self::assertSame(0, $this->history->countForUser($userId));
    }

    private function movie(string $requestKey, string $watchedOn): WatchHistoryEvent
    {
        return WatchHistoryEvent::movie(603, 'Matrix', 'The Matrix', '1999-03-31', '/matrix.jpg', 136, $watchedOn, $requestKey);
    }

    private function episode(string $requestKey, string $watchedOn): WatchHistoryEvent
    {
        return WatchHistoryEvent::episode(1396, 1, 1, 'Breaking Bad', 'Breaking Bad', 'Piloto', '2008-01-20', '/bb.jpg', 47, $watchedOn, $requestKey);
    }

    private function clear(): void
    {
        $pdo = $this->database->connection();
        $pdo->exec('DELETE FROM watch_history');
        $pdo->exec('DELETE FROM user_series_episode_progress');
        $pdo->exec('DELETE FROM user_media');
        $pdo->exec('DELETE FROM user_external_identities');
        $pdo->exec('DELETE FROM user_profiles');
        $pdo->exec('DELETE FROM users');
    }
}
