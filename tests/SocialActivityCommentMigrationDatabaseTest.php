<?php

declare(strict_types=1);

namespace Tests;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class SocialActivityCommentMigrationDatabaseTest extends TestCase
{
    public function testMigrationCreatesExpectedSchemaAndCascades(): void
    {
        $configured = getenv('FLICKARY_TEST_DB_DATABASE');
        if (!is_string($configured) || !str_ends_with($configured, '_test')) {
            self::markTestSkipped('Disposable Flickary database was not configured.');
        }
        $pdo = new PDO(
            'mysql:host=' . (getenv('FLICKARY_TEST_DB_HOST') ?: '127.0.0.1') . ';port=' . (int) (getenv('FLICKARY_TEST_DB_PORT') ?: 3306) . ';charset=utf8mb4',
            getenv('FLICKARY_TEST_DB_USERNAME') ?: '', getenv('FLICKARY_TEST_DB_PASSWORD') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
        );
        $database = 'flickary_comment_migration_' . bin2hex(random_bytes(5));
        $quoted = '`' . $database . '`';
        $pdo->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        try {
            $pdo->exec("USE {$quoted}");
            $root = dirname(__DIR__);
            foreach (['2026_09_16_000001_create_account_foundation.sql', '2026_09_18_000004_create_watch_history.sql', '2026_11_16_000012_create_social_activity_likes.sql', '2026_11_16_000013_create_social_activity_comments.sql'] as $migration) {
                $pdo->exec((string) file_get_contents($root . '/database/migrations/' . $migration));
            }
            $columns = $pdo->query("SELECT column_name,column_type,is_nullable FROM information_schema.columns WHERE table_schema='{$database}' AND table_name='social_activity_comments' ORDER BY ordinal_position")->fetchAll(PDO::FETCH_ASSOC);
            self::assertSame(['id','history_id','user_id','body','created_at'], array_column($columns, 'column_name'));
            self::assertSame('varchar(500)', strtolower((string) $columns[3]['column_type']));
            self::assertSame('NO', $columns[3]['is_nullable']);
            foreach (['idx_social_activity_comments_history' => 'history_id,created_at,id', 'idx_social_activity_comments_user' => 'user_id,created_at,id'] as $name => $expected) {
                $actual = $pdo->query("SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index) FROM information_schema.statistics WHERE table_schema='{$database}' AND table_name='social_activity_comments' AND index_name='{$name}'")->fetchColumn();
                self::assertSame($expected, $actual);
            }
            $foreignKeys = $pdo->query("SELECT constraint_name,referenced_table_name,delete_rule FROM information_schema.referential_constraints WHERE constraint_schema='{$database}' AND table_name='social_activity_comments' ORDER BY constraint_name")->fetchAll(PDO::FETCH_ASSOC);
            self::assertSame([
                ['constraint_name'=>'fk_social_activity_comments_history','referenced_table_name'=>'watch_history','delete_rule'=>'CASCADE'],
                ['constraint_name'=>'fk_social_activity_comments_user','referenced_table_name'=>'users','delete_rule'=>'CASCADE'],
            ], $foreignKeys);

            $pdo->exec("INSERT INTO users(username) VALUES ('comment.actor'),('comment.author')");
            $pdo->exec("INSERT INTO watch_history(user_id,source,entry_type,source_id,title,watched_on,request_key) VALUES (1,'tmdb','movie',603,'Matrix','2026-01-01','" . str_repeat('c', 32) . "')");
            $body500 = str_repeat('á', 500);
            $insert = $pdo->prepare('INSERT INTO social_activity_comments(history_id,user_id,body) VALUES (1,2,?),(1,2,?)');
            $insert->execute([$body500, 'Segundo comentário']);
            self::assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM social_activity_comments')->fetchColumn());
            try {
                $pdo->prepare('INSERT INTO social_activity_comments(history_id,user_id,body) VALUES (1,2,?)')->execute([str_repeat('a', 501)]);
                self::fail('VARCHAR(500) must reject oversized comments in strict mode.');
            } catch (PDOException $exception) {
                self::assertContains((int) ($exception->errorInfo[1] ?? 0), [1406, 1265]);
            }
            $pdo->exec('DELETE FROM watch_history WHERE id=1');
            self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM social_activity_comments')->fetchColumn());
        } finally {
            $pdo->exec("DROP DATABASE IF EXISTS {$quoted}");
        }
    }
}
