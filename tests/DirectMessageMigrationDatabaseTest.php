<?php

declare(strict_types=1);

namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase;

final class DirectMessageMigrationDatabaseTest extends TestCase
{
    public function testSchemaIndexesAndCascades(): void
    {
        $configured = getenv('FLICKARY_TEST_DB_DATABASE');
        if (!is_string($configured) || !str_ends_with($configured, '_test')) { self::markTestSkipped('Disposable Flickary database was not configured.'); }
        $pdo = new PDO('mysql:host=' . (getenv('FLICKARY_TEST_DB_HOST') ?: '127.0.0.1') . ';port=' . (int) (getenv('FLICKARY_TEST_DB_PORT') ?: 3306) . ';charset=utf8mb4', getenv('FLICKARY_TEST_DB_USERNAME') ?: '', getenv('FLICKARY_TEST_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
        $database = 'flickary_messages_migration_' . bin2hex(random_bytes(5)); $quoted = '`' . $database . '`';
        $pdo->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        try {
            $pdo->exec("USE {$quoted}"); $root = dirname(__DIR__);
            foreach (['2026_09_16_000001_create_account_foundation.sql', '2026_11_12_000009_create_user_follows.sql', '2026_11_17_000015_create_direct_messages.sql'] as $migration) {
                $pdo->exec((string) file_get_contents($root . '/database/migrations/' . $migration));
            }
            $conversationColumns = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema='{$database}' AND table_name='direct_conversations' ORDER BY ordinal_position")->fetchAll(PDO::FETCH_COLUMN);
            self::assertSame(['id','user_low_id','user_high_id','created_at','last_message_at'], $conversationColumns);
            $messageColumns = $pdo->query("SELECT column_name,column_type,is_nullable FROM information_schema.columns WHERE table_schema='{$database}' AND table_name='direct_messages' ORDER BY ordinal_position")->fetchAll(PDO::FETCH_ASSOC);
            self::assertSame(['id','conversation_id','sender_user_id','body','read_at','created_at'], array_column($messageColumns, 'column_name'));
            self::assertSame('varchar(2000)', $messageColumns[3]['column_type']); self::assertSame('YES', $messageColumns[4]['is_nullable']);
            $pair = $pdo->query("SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index) FROM information_schema.statistics WHERE table_schema='{$database}' AND table_name='direct_conversations' AND index_name='uq_direct_conversations_pair'")->fetchColumn();
            self::assertSame('user_low_id,user_high_id', $pair);
            $unread = $pdo->query("SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index) FROM information_schema.statistics WHERE table_schema='{$database}' AND table_name='direct_messages' AND index_name='idx_direct_messages_unread'")->fetchColumn();
            self::assertSame('conversation_id,sender_user_id,read_at,id', $unread);
            $fks = $pdo->query("SELECT table_name,COUNT(*) AS amount FROM information_schema.referential_constraints WHERE constraint_schema='{$database}' AND table_name IN ('direct_conversations','direct_messages') AND delete_rule='CASCADE' GROUP BY table_name ORDER BY table_name")->fetchAll(PDO::FETCH_KEY_PAIR);
            self::assertSame(['direct_conversations' => 2, 'direct_messages' => 2], array_map('intval', $fks));
            $pdo->exec("INSERT INTO users(username) VALUES ('message.one'),('message.two')");
            $pdo->exec('INSERT INTO direct_conversations(user_low_id,user_high_id) VALUES (1,2)');
            $pdo->exec("INSERT INTO direct_messages(conversation_id,sender_user_id,body) VALUES (1,1,'hello')");
            $pdo->exec('DELETE FROM users WHERE id=1');
            self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM direct_conversations')->fetchColumn());
            self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM direct_messages')->fetchColumn());
        } finally { $pdo->exec("DROP DATABASE IF EXISTS {$quoted}"); }
    }
}
