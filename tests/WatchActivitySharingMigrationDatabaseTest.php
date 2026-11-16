<?php

declare(strict_types=1);

namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase;

final class WatchActivitySharingMigrationDatabaseTest extends TestCase
{
    public function testMigrationKeepsExistingProfilesOptedOutAndAddsFeedIndex(): void
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
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
        );
        $database = 'flickary_social_migration_' . bin2hex(random_bytes(5));
        $quoted = '`' . $database . '`';
        $pdo->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        try {
            $pdo->exec("USE {$quoted}");
            $root = dirname(__DIR__);
            $pdo->exec((string) file_get_contents($root . '/database/migrations/2026_09_16_000001_create_account_foundation.sql'));
            $pdo->exec((string) file_get_contents($root . '/database/migrations/2026_09_18_000004_create_watch_history.sql'));
            $pdo->exec("INSERT INTO users(username) VALUES ('existing.user')");
            $pdo->exec("INSERT INTO user_profiles(user_id,display_name) VALUES (1,'Existing')");
            $pdo->exec("INSERT INTO watch_history(user_id,source,entry_type,source_id,title,watched_on,request_key) VALUES (1,'tmdb','movie',603,'Matrix','2026-01-01','" . str_repeat('a', 32) . "')");
            $pdo->exec((string) file_get_contents($root . '/database/migrations/2026_11_16_000011_add_watch_activity_sharing.sql'));

            $profile = $pdo->query('SELECT share_watch_activity,share_watch_activity_since FROM user_profiles WHERE user_id=1')->fetch(PDO::FETCH_ASSOC);
            self::assertSame(0, (int) $profile['share_watch_activity']);
            self::assertNull($profile['share_watch_activity_since']);
            $columns = $pdo->query("SELECT column_name,column_default,is_nullable FROM information_schema.columns WHERE table_schema='{$database}' AND table_name='user_profiles' AND column_name IN ('share_watch_activity','share_watch_activity_since') ORDER BY ordinal_position")->fetchAll(PDO::FETCH_ASSOC);
            self::assertSame(['share_watch_activity', 'share_watch_activity_since'], array_column($columns, 'column_name'));
            self::assertSame('0', (string) $columns[0]['column_default']);
            self::assertSame('NO', $columns[0]['is_nullable']);
            self::assertContains($columns[1]['column_default'], [null, 'NULL']);
            self::assertSame('YES', $columns[1]['is_nullable']);
            $index = $pdo->query("SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index) FROM information_schema.statistics WHERE table_schema='{$database}' AND table_name='watch_history' AND index_name='idx_watch_history_social_feed'")->fetchColumn();
            self::assertSame('user_id,created_at,id', $index);
        } finally {
            $pdo->exec("DROP DATABASE IF EXISTS {$quoted}");
        }
    }
}
