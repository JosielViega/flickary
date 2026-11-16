<?php

declare(strict_types=1);

namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase;

final class FollowRequestMigrationDatabaseTest extends TestCase
{
    public function testLegacyPublicFollowStaysAndPrivateFollowBecomesPending(): void
    {
        $configured = getenv('FLICKARY_TEST_DB_DATABASE');
        if (!is_string($configured) || !str_ends_with($configured, '_test')) {
            self::markTestSkipped('Disposable Flickary database was not configured.');
        }
        $host = getenv('FLICKARY_TEST_DB_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('FLICKARY_TEST_DB_PORT') ?: 3306);
        $pdo = new PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            getenv('FLICKARY_TEST_DB_USERNAME') ?: '',
            getenv('FLICKARY_TEST_DB_PASSWORD') ?: '',
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false],
        );
        $database = 'flickary_follow_migration_' . bin2hex(random_bytes(5));
        $quoted = '`' . $database . '`';
        $pdo->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        try {
            $pdo->exec("USE {$quoted}");
            $root = dirname(__DIR__);
            $pdo->exec((string) file_get_contents($root . '/database/migrations/2026_09_16_000001_create_account_foundation.sql'));
            $pdo->exec((string) file_get_contents($root . '/database/migrations/2026_11_12_000009_create_user_follows.sql'));
            $pdo->exec("INSERT INTO users (username) VALUES ('public.target'),('private.target'),('legacy.follower')");
            $pdo->exec("INSERT INTO user_profiles (user_id,display_name,is_private) VALUES (1,'Public',0),(2,'Private',1),(3,'Follower',0)");
            $pdo->exec('INSERT INTO user_follows (follower_user_id,followed_user_id) VALUES (3,1),(3,2)');
            $pdo->exec((string) file_get_contents($root . '/database/migrations/2026_11_16_000010_create_user_follow_requests.sql'));
            self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM user_follows WHERE follower_user_id=3 AND followed_user_id=1')->fetchColumn());
            self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM user_follow_requests WHERE requester_user_id=3 AND target_user_id=1')->fetchColumn());
            self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM user_follows WHERE follower_user_id=3 AND followed_user_id=2')->fetchColumn());
            self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM user_follow_requests WHERE requester_user_id=3 AND target_user_id=2')->fetchColumn());
        } finally {
            $pdo->exec("DROP DATABASE IF EXISTS {$quoted}");
        }
    }
}
