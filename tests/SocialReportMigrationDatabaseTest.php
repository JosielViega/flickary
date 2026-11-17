<?php

declare(strict_types=1);

namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase;

final class SocialReportMigrationDatabaseTest extends TestCase
{
    public function testAdminFlagReportsKeysIndexesAndDeleteRules(): void
    {
        $configured=getenv('FLICKARY_TEST_DB_DATABASE');
        if(!is_string($configured)||!str_ends_with($configured,'_test')){self::markTestSkipped('Disposable Flickary database was not configured.');}
        $pdo=new PDO('mysql:host='.(getenv('FLICKARY_TEST_DB_HOST')?:'127.0.0.1').';port='.(int)(getenv('FLICKARY_TEST_DB_PORT')?:3306).';charset=utf8mb4',getenv('FLICKARY_TEST_DB_USERNAME')?:'',getenv('FLICKARY_TEST_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
        $database='flickary_reports_migration_'.bin2hex(random_bytes(5));$quoted='`'.$database.'`';$pdo->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        try{
            $pdo->exec("USE {$quoted}");$root=dirname(__DIR__);
            foreach(['2026_09_16_000001_create_account_foundation.sql','2026_11_17_000017_create_reports_and_admin_role.sql']as$file){$pdo->exec((string)file_get_contents($root.'/database/migrations/'.$file));}
            $column=$pdo->query("SELECT column_default,is_nullable FROM information_schema.columns WHERE table_schema='{$database}' AND table_name='users' AND column_name='is_admin'")->fetch(PDO::FETCH_ASSOC);
            self::assertSame('0',(string)$column['column_default']);self::assertSame('NO',$column['is_nullable']);
            $indexes=$pdo->query("SELECT index_name,GROUP_CONCAT(column_name ORDER BY seq_in_index) columns_list FROM information_schema.statistics WHERE table_schema='{$database}' AND table_name='social_reports' GROUP BY index_name")->fetchAll(PDO::FETCH_KEY_PAIR);
            self::assertSame('reporter_user_id,dedupe_key',$indexes['uq_social_reports_pending']);
            self::assertSame('status,created_at,id',$indexes['idx_social_reports_status_created']);
            self::assertSame('reporter_user_id,created_at,id',$indexes['idx_social_reports_reporter_created']);
            self::assertSame('reported_user_id,created_at,id',$indexes['idx_social_reports_reported_created']);
            $rules=$pdo->query("SELECT constraint_name,delete_rule FROM information_schema.referential_constraints WHERE constraint_schema='{$database}' AND table_name='social_reports'")->fetchAll(PDO::FETCH_KEY_PAIR);
            self::assertSame('CASCADE',$rules['fk_social_reports_reporter']);self::assertSame('SET NULL',$rules['fk_social_reports_reported']);self::assertSame('SET NULL',$rules['fk_social_reports_reviewer']);
            self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM social_reports')->fetchColumn());
        }finally{$pdo->exec("DROP DATABASE IF EXISTS {$quoted}");}
    }
}
