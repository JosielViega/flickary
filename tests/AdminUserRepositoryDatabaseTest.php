<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Repositories\AdminUserRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use PHPUnit\Framework\TestCase;

final class AdminUserRepositoryDatabaseTest extends TestCase
{
    private Database$database;private AdminUserRepository$admins;
    protected function setUp():void{$name=getenv('FLICKARY_TEST_DB_DATABASE');if(!is_string($name)||!str_ends_with($name,'_test')){self::markTestSkipped('Disposable Flickary database was not configured.');}$this->database=new Database(['host'=>getenv('FLICKARY_TEST_DB_HOST')?:'127.0.0.1','port'=>(int)(getenv('FLICKARY_TEST_DB_PORT')?:3306),'database'=>$name,'username'=>getenv('FLICKARY_TEST_DB_USERNAME')?:'','password'=>getenv('FLICKARY_TEST_DB_PASSWORD')?:'','charset'=>'utf8mb4']);$this->database->connection()->exec('DELETE FROM users');$users=new UserRepository($this->database);$profiles=new UserProfileRepository($this->database);$id=$users->create('admin.test',null);$profiles->create($id,'Admin',null);$this->admins=new AdminUserRepository($this->database);}
    protected function tearDown():void{if(isset($this->database)){$this->database->connection()->exec('DELETE FROM users');}}
    public function testGrantListAndRevokeAreDatabaseBackedAndIdempotent():void{$id=(int)$this->database->connection()->query("SELECT id FROM users WHERE username='admin.test'")->fetchColumn();self::assertFalse($this->admins->isAdmin($id));self::assertTrue($this->admins->setAdmin('admin.test',true));self::assertTrue($this->admins->setAdmin('admin.test',true));self::assertTrue($this->admins->isAdmin($id));self::assertSame(['admin.test'],$this->admins->adminUsernames());self::assertTrue($this->admins->setAdmin('admin.test',false));self::assertTrue($this->admins->setAdmin('admin.test',false));self::assertFalse($this->admins->isAdmin($id));self::assertSame([],$this->admins->adminUsernames());self::assertFalse($this->admins->setAdmin('missing.user',true));}
}
