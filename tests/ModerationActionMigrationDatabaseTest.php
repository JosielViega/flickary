<?php

declare(strict_types=1);

namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase;

final class ModerationActionMigrationDatabaseTest extends TestCase
{
    public function testSuspensionColumnActionTableForeignKeysAndIndexes():void
    {
        $configured=getenv('FLICKARY_TEST_DB_DATABASE');if(!is_string($configured)||!str_ends_with($configured,'_test'))self::markTestSkipped('Disposable Flickary database was not configured.');
        $pdo=new PDO('mysql:host='.(getenv('FLICKARY_TEST_DB_HOST')?:'127.0.0.1').';port='.(int)(getenv('FLICKARY_TEST_DB_PORT')?:3306).';charset=utf8mb4',getenv('FLICKARY_TEST_DB_USERNAME')?:'',getenv('FLICKARY_TEST_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
        $database='flickary_moderation_migration_'.bin2hex(random_bytes(5));$quoted='`'.$database.'`';$pdo->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        try{$pdo->exec("USE {$quoted}");$root=dirname(__DIR__);foreach(['2026_09_16_000001_create_account_foundation.sql','2026_11_17_000017_create_reports_and_admin_role.sql','2026_11_17_000018_add_moderation_actions.sql']as$file)$pdo->exec((string)file_get_contents($root.'/database/migrations/'.$file));
            $column=$pdo->query("SELECT column_default,is_nullable,data_type FROM information_schema.columns WHERE table_schema='{$database}' AND table_name='users' AND column_name='suspended_until'")->fetch(PDO::FETCH_ASSOC);self::assertSame('NULL',(string)$column['column_default']);self::assertSame('YES',$column['is_nullable']);self::assertSame('timestamp',$column['data_type']);
            $indexes=$pdo->query("SELECT index_name,GROUP_CONCAT(column_name ORDER BY seq_in_index) columns_list FROM information_schema.statistics WHERE table_schema='{$database}' AND table_name='moderation_actions' GROUP BY index_name")->fetchAll(PDO::FETCH_KEY_PAIR);
            self::assertSame('target_user_id,created_at,id',$indexes['idx_moderation_actions_target_created']);self::assertSame('performed_by_user_id,created_at,id',$indexes['idx_moderation_actions_admin_created']);self::assertSame('report_id',$indexes['idx_moderation_actions_report']);self::assertSame('action_type,created_at,id',$indexes['idx_moderation_actions_type_created']);self::assertSame('created_at,id',$indexes['idx_moderation_actions_created']);
            $rules=$pdo->query("SELECT constraint_name,delete_rule FROM information_schema.referential_constraints WHERE constraint_schema='{$database}' AND table_name='moderation_actions'")->fetchAll(PDO::FETCH_KEY_PAIR);self::assertSame('SET NULL',$rules['fk_moderation_actions_report']);self::assertSame('SET NULL',$rules['fk_moderation_actions_target']);self::assertSame('SET NULL',$rules['fk_moderation_actions_admin']);
            $columns=$pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema='{$database}' AND table_name='moderation_actions'")->fetchAll(PDO::FETCH_COLUMN);foreach(['action_type','target_username_snapshot','target_display_name_snapshot','content_snapshot','duration_minutes','previous_suspended_until','effective_until','note']as$name)self::assertContains($name,$columns);
        }finally{$pdo->exec("DROP DATABASE IF EXISTS {$quoted}");}
    }
}
