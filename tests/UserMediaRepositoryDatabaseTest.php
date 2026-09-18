<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Integrations\Tmdb\TmdbMediaDetails;
use App\Repositories\UserMediaRepository;
use App\Repositories\UserRepository;
use PHPUnit\Framework\TestCase;

final class UserMediaRepositoryDatabaseTest extends TestCase
{
    private Database $database;
    private UserMediaRepository $media;
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
        $this->media = new UserMediaRepository($this->database);
        $this->users = new UserRepository($this->database);
        $this->clear();
    }

    protected function tearDown(): void
    {
        if (isset($this->database)) { $this->clear(); }
    }

    public function testSchemaHasForeignKeyUniqueAndExpectedIndexes(): void
    {
        $pdo = $this->database->connection();
        $indexes = $pdo->query('SHOW INDEX FROM user_media')->fetchAll();
        $names = array_unique(array_column($indexes, 'Key_name'));
        self::assertContains('uq_user_media_identity', $names);
        self::assertContains('idx_user_media_status_updated', $names);
        self::assertContains('idx_user_media_type_updated', $names);

        $create = (string) $pdo->query('SHOW CREATE TABLE user_media')->fetchColumn(1);
        self::assertStringContainsString('FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE', $create);
    }

    public function testCreateUniquePerUserAndAllowSameMediaForDifferentUsers(): void
    {
        $first = $this->users->create('media.one', null);
        $second = $this->users->create('media.two', null);
        $details = $this->details(603, 'movie', 'Matrix');

        self::assertTrue($this->media->create($first, $details, 'planned'));
        self::assertFalse($this->media->create($first, $details, 'watching'));
        self::assertTrue($this->media->create($second, $details, 'watching'));
        self::assertSame('planned', $this->media->findForUser($first, 'tmdb', 'movie', 603)?->status);
        self::assertSame('watching', $this->media->findForUser($second, 'tmdb', 'movie', 603)?->status);
    }

    public function testOwnershipUpdateDeleteFiltersAndSqlPagination(): void
    {
        $first = $this->users->create('media.owner', null);
        $second = $this->users->create('media.other', null);
        $this->media->create($first, $this->details(603, 'movie', 'Matrix'), 'planned');
        $this->media->create($first, $this->details(1396, 'series', 'Breaking Bad'), 'watching');
        $this->media->create($first, $this->details(550, 'movie', 'Fight Club'), 'completed');
        $this->media->create($second, $this->details(603, 'movie', 'Matrix'), 'dropped');

        self::assertFalse($this->media->updateStatus($second, 'tmdb', 'series', 1396, 'paused'));
        self::assertTrue($this->media->updateStatus($first, 'tmdb', 'series', 1396, 'paused'));
        self::assertSame('paused', $this->media->findForUser($first, 'tmdb', 'series', 1396)?->status);
        self::assertSame(3, $this->media->countForUser($first));

        $movies = $this->media->paginateForUser($first, null, 'movie', 1, 1);
        self::assertSame(2, $movies->total);
        self::assertCount(1, $movies->items);
        self::assertSame(2, $movies->lastPage());
        $paused = $this->media->paginateForUser($first, 'paused', null, 1, 24);
        self::assertSame([1396], array_map(static fn ($item): int => $item->sourceId, $paused->items));

        self::assertFalse($this->media->delete($second, 'tmdb', 'series', 1396));
        self::assertTrue($this->media->delete($first, 'tmdb', 'series', 1396));
        self::assertNull($this->media->findForUser($first, 'tmdb', 'series', 1396));
        self::assertNotNull($this->media->findForUser($second, 'tmdb', 'movie', 603));
    }

    public function testDeletingUserCascadesPersonalList(): void
    {
        $userId = $this->users->create('media.cascade', null);
        $this->media->create($userId, $this->details(603, 'movie', 'Matrix'), 'planned');
        $statement = $this->database->connection()->prepare('DELETE FROM users WHERE id = :id');
        $statement->execute(['id' => $userId]);
        self::assertSame(0, $this->media->countForUser($userId));
    }

    private function details(int $id, string $type, string $title): TmdbMediaDetails
    {
        return new TmdbMediaDetails('tmdb', $type, $id, $title, $title, null, null, '1999-03-31', 1999, '/poster.jpg', '/backdrop.jpg', [], null, null, null, 'en', false, null, null, null, null, null, null);
    }

    private function clear(): void
    {
        $pdo = $this->database->connection();
        $pdo->exec('DELETE FROM user_media');
        $pdo->exec('DELETE FROM user_external_identities');
        $pdo->exec('DELETE FROM user_profiles');
        $pdo->exec('DELETE FROM users');
    }
}
