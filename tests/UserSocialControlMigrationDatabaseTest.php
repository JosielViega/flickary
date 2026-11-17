<?php

declare(strict_types=1);

namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase;

final class UserSocialControlMigrationDatabaseTest extends TestCase
{
    public function testSchemaHasCompositeKeysReverseIndexesAndCascadeForeignKeys():void
    {
        $configured=getenv('FLICKARY_TEST_DB_DATABASE');if(!is_string($configured)||!str_ends_with($configured,'_test')){self::markTestSkipped('Disposable Flickary database was not configured.');}
        $pdo=new PDO('mysql:host='.(getenv('FLICKARY_TEST_DB_HOST')?:'127.0.0.1').';port='.(int)(getenv('FLICKARY_TEST_DB_PORT')?:3306).';charset=utf8mb4',getenv('FLICKARY_TEST_DB_USERNAME')?:'',getenv('FLICKARY_TEST_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
        $database='flickary_controls_migration_'.bin2hex(random_bytes(5));$quoted='`'.$database.'`';$pdo->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        try{$pdo->exec("USE {$quoted}");$root=dirname(__DIR__);foreach(['2026_09_16_000001_create_account_foundation.sql','2026_11_17_000016_create_user_social_controls.sql']as$migration){$pdo->exec((string)file_get_contents($root.'/database/migrations/'.$migration));}
            foreach(['user_blocks'=>['PRIMARY'=>'blocker_user_id,blocked_user_id','idx_user_blocks_blocked'=>'blocked_user_id,blocker_user_id'],'user_mutes'=>['PRIMARY'=>'user_id,muted_user_id','idx_user_mutes_muted'=>'muted_user_id,user_id']]as$table=>$indexes){foreach($indexes as$name=>$expected){$actual=$pdo->query("SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index) FROM information_schema.statistics WHERE table_schema='{$database}' AND table_name='{$table}' AND index_name='{$name}'")->fetchColumn();self::assertSame($expected,$actual);}}
            $fks=$pdo->query("SELECT table_name,COUNT(*) amount FROM information_schema.referential_constraints WHERE constraint_schema='{$database}' AND table_name IN ('user_blocks','user_mutes') AND delete_rule='CASCADE' GROUP BY table_name ORDER BY table_name")->fetchAll(PDO::FETCH_KEY_PAIR);self::assertSame(['user_blocks'=>2,'user_mutes'=>2],array_map('intval',$fks));
            self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM user_blocks')->fetchColumn());self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM user_mutes')->fetchColumn());
        }finally{$pdo->exec("DROP DATABASE IF EXISTS {$quoted}");}
    }
}
