<?php

declare(strict_types=1);

namespace Tests;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class SocialActivityLikeMigrationDatabaseTest extends TestCase
{
    public function testMigrationCreatesCompositeKeyForeignKeysCascadesAndUserIndex(): void
    {
        $configured = getenv('FLICKARY_TEST_DB_DATABASE');
        if (!is_string($configured) || !str_ends_with($configured, '_test')) {
            self::markTestSkipped('Disposable Flickary database was not configured.');
        }
        $pdo = new PDO(
            'mysql:host=' . (getenv('FLICKARY_TEST_DB_HOST') ?: '127.0.0.1')
                . ';port=' . (int) (getenv('FLICKARY_TEST_DB_PORT') ?: 3306) . ';charset=utf8mb4',
            getenv('FLICKARY_TEST_DB_USERNAME') ?: '',
            getenv('FLICKARY_TEST_DB_PASSWORD') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
        );
        $database = 'flickary_like_migration_' . bin2hex(random_bytes(5));
        $quoted = '`' . $database . '`';
        $pdo->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        try {
            $pdo->exec("USE {$quoted}");
            $root = dirname(__DIR__);
            $pdo->exec((string) file_get_contents($root . '/database/migrations/2026_09_16_000001_create_account_foundation.sql'));
            $pdo->exec((string) file_get_contents($root . '/database/migrations/2026_09_18_000004_create_watch_history.sql'));
            $pdo->exec((string) file_get_contents($root . '/database/migrations/2026_11_16_000012_create_social_activity_likes.sql'));

            $primary = $pdo->query("SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index) FROM information_schema.statistics WHERE table_schema='{$database}' AND table_name='social_activity_likes' AND index_name='PRIMARY'")->fetchColumn();
            self::assertSame('history_id,user_id', $primary);
            $userIndex = $pdo->query("SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index) FROM information_schema.statistics WHERE table_schema='{$database}' AND table_name='social_activity_likes' AND index_name='idx_social_activity_likes_user'")->fetchColumn();
            self::assertSame('user_id,created_at', $userIndex);
            $foreignKeys = $pdo->query("SELECT constraint_name,referenced_table_name,delete_rule FROM information_schema.referential_constraints WHERE constraint_schema='{$database}' AND table_name='social_activity_likes' ORDER BY constraint_name")->fetchAll(PDO::FETCH_ASSOC);
            self::assertSame([
                ['constraint_name' => 'fk_social_activity_likes_history', 'referenced_table_name' => 'watch_history', 'delete_rule' => 'CASCADE'],
                ['constraint_name' => 'fk_social_activity_likes_user', 'referenced_table_name' => 'users', 'delete_rule' => 'CASCADE'],
            ], $foreignKeys);

            $pdo->exec("INSERT INTO users(username) VALUES ('actor.like'),('liker.like')");
            $pdo->exec("INSERT INTO watch_history(user_id,source,entry_type,source_id,title,watched_on,request_key) VALUES (1,'tmdb','movie',603,'Matrix','2026-01-01','" . str_repeat('a', 32) . "')");
            $pdo->exec('INSERT INTO social_activity_likes(history_id,user_id) VALUES (1,2)');
            try {
                $pdo->exec('INSERT INTO social_activity_likes(history_id,user_id) VALUES (1,2)');
                self::fail('Composite primary key must reject duplicate likes.');
            } catch (PDOException $exception) {
                self::assertSame(1062, (int) ($exception->errorInfo[1] ?? 0));
            }
            $pdo->exec('DELETE FROM watch_history WHERE id=1');
            self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM social_activity_likes')->fetchColumn());
        } finally {
            $pdo->exec("DROP DATABASE IF EXISTS {$quoted}");
        }
    }
}
