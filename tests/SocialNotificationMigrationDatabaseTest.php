<?php

declare(strict_types=1);

namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase;

final class SocialNotificationMigrationDatabaseTest extends TestCase
{
    public function testSchemaCascadesAndOnlyPendingRequestsAreBackfilled():void
    {
        $configured=getenv('FLICKARY_TEST_DB_DATABASE');if(!is_string($configured)||!str_ends_with($configured,'_test')){self::markTestSkipped('Disposable Flickary database was not configured.');}
        $pdo=new PDO('mysql:host='.(getenv('FLICKARY_TEST_DB_HOST')?:'127.0.0.1').';port='.(int)(getenv('FLICKARY_TEST_DB_PORT')?:3306).';charset=utf8mb4',getenv('FLICKARY_TEST_DB_USERNAME')?:'',getenv('FLICKARY_TEST_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
        $database='flickary_notification_migration_'.bin2hex(random_bytes(5));$quoted='`'.$database.'`';$pdo->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        try{
            $pdo->exec("USE {$quoted}");$root=dirname(__DIR__);
            foreach(['2026_09_16_000001_create_account_foundation.sql','2026_09_18_000004_create_watch_history.sql','2026_11_12_000009_create_user_follows.sql','2026_11_16_000010_create_user_follow_requests.sql','2026_11_16_000011_add_watch_activity_sharing.sql','2026_11_16_000012_create_social_activity_likes.sql','2026_11_16_000013_create_social_activity_comments.sql']as$migration){$pdo->exec((string)file_get_contents($root.'/database/migrations/'.$migration));}
            $pdo->exec("INSERT INTO users(username) VALUES ('pending.actor'),('pending.target'),('old.actor')");
            $pdo->exec("INSERT INTO user_profiles(user_id,display_name,is_private) VALUES (1,'Actor',0),(2,'Target',1),(3,'Old',0)");
            $pdo->exec("INSERT INTO user_follow_requests(requester_user_id,target_user_id,created_at) VALUES (1,2,'2026-11-01 10:00:00')");
            $pdo->exec("INSERT INTO user_follows(follower_user_id,followed_user_id) VALUES (3,1)");
            $pdo->exec("INSERT INTO watch_history(user_id,source,entry_type,source_id,title,watched_on,request_key) VALUES (1,'tmdb','movie',603,'Matrix','2026-01-01','".str_repeat('n',32)."')");
            $pdo->exec("INSERT INTO social_activity_likes(history_id,user_id) VALUES (1,3)");
            $pdo->exec("INSERT INTO social_activity_comments(history_id,user_id,body) VALUES (1,3,'Old comment')");
            $pdo->exec((string)file_get_contents($root.'/database/migrations/2026_11_16_000014_create_social_notifications.sql'));

            $columns=$pdo->query("SELECT column_name,is_nullable FROM information_schema.columns WHERE table_schema='{$database}' AND table_name='social_notifications' ORDER BY ordinal_position")->fetchAll(PDO::FETCH_ASSOC);
            self::assertSame(['id','recipient_user_id','actor_user_id','type','history_id','comment_id','read_at','created_at'],array_column($columns,'column_name'));
            self::assertSame('YES',$columns[6]['is_nullable']);
            self::assertSame([['type'=>'follow_request','recipient_user_id'=>2,'actor_user_id'=>1,'history_id'=>null,'comment_id'=>null]],$pdo->query('SELECT type,recipient_user_id,actor_user_id,history_id,comment_id FROM social_notifications')->fetchAll(PDO::FETCH_ASSOC));
            $index=$pdo->query("SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index) FROM information_schema.statistics WHERE table_schema='{$database}' AND table_name='social_notifications' AND index_name='idx_social_notifications_recipient'")->fetchColumn();
            self::assertSame('recipient_user_id,read_at,created_at,id',$index);
            $fks=$pdo->query("SELECT referenced_table_name,delete_rule FROM information_schema.referential_constraints WHERE constraint_schema='{$database}' AND table_name='social_notifications' ORDER BY constraint_name")->fetchAll(PDO::FETCH_ASSOC);
            self::assertSame([['referenced_table_name'=>'users','delete_rule'=>'CASCADE'],['referenced_table_name'=>'social_activity_comments','delete_rule'=>'CASCADE'],['referenced_table_name'=>'watch_history','delete_rule'=>'CASCADE'],['referenced_table_name'=>'users','delete_rule'=>'CASCADE']],$fks);
            $pdo->exec("INSERT INTO social_notifications(recipient_user_id,actor_user_id,type,history_id,comment_id) VALUES (1,3,'activity_comment',1,1)");
            $pdo->exec('DELETE FROM social_activity_comments WHERE id=1');
            self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM social_notifications')->fetchColumn());
            $pdo->exec('DELETE FROM users WHERE id=1');
            self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM social_notifications')->fetchColumn());
        }finally{$pdo->exec("DROP DATABASE IF EXISTS {$quoted}");}
    }
}
