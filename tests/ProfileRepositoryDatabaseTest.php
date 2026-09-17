<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use PHPUnit\Framework\TestCase;

final class ProfileRepositoryDatabaseTest extends TestCase
{
    private Database $database;
    private UserProfileRepository $profiles;

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
        $this->profiles = new UserProfileRepository($this->database);
        $this->clearAccounts();
    }

    protected function tearDown(): void
    {
        if (isset($this->database)) {
            $this->clearAccounts();
        }
    }

    public function testReadsAndUpdatesOnlyProfileFieldsForUser(): void
    {
        $users = new UserRepository($this->database);
        $userId = $users->create('profile.repo', 'private@example.com');
        $this->profiles->create($userId, 'Original Name', 'https://example.test/avatar.jpg');

        $profile = $this->profiles->findByUserId($userId);
        self::assertNotNull($profile);
        self::assertSame('profile.repo', $profile['username']);
        self::assertSame('Original Name', $profile['display_name']);
        self::assertArrayNotHasKey('email', $profile);

        $this->profiles->updateProfile($userId, 'Updated Name', 'Updated bio', true);
        $updated = $this->profiles->findByUserId($userId);
        self::assertSame('Updated Name', $updated['display_name']);
        self::assertSame('Updated bio', $updated['bio']);
        self::assertSame(1, $updated['is_private']);
        self::assertSame('profile.repo', $updated['username']);
        self::assertSame('https://example.test/avatar.jpg', $updated['avatar_url']);
        self::assertNull($updated['cover_url']);
        self::assertNull($this->profiles->findByUserId($userId + 999));
    }

    private function clearAccounts(): void
    {
        $connection = $this->database->connection();
        $connection->exec('DELETE FROM user_external_identities');
        $connection->exec('DELETE FROM user_profiles');
        $connection->exec('DELETE FROM users');
    }
}
