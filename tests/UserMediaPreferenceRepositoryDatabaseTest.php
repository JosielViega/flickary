<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Preferences\UserMediaPreferenceDraft;
use App\Repositories\UserMediaPreferenceRepository;
use App\Repositories\UserRepository;
use PHPUnit\Framework\TestCase;

final class UserMediaPreferenceRepositoryDatabaseTest extends TestCase
{
    private Database$db;private UserMediaPreferenceRepository$preferences;private UserRepository$users;
    protected function setUp():void{$name=getenv('FLICKARY_TEST_DB_DATABASE');if(!is_string($name)||!str_ends_with($name,'_test'))self::markTestSkipped('Disposable Flickary database was not configured.');$this->db=new Database(['host'=>getenv('FLICKARY_TEST_DB_HOST')?:'127.0.0.1','port'=>(int)(getenv('FLICKARY_TEST_DB_PORT')?:3306),'database'=>$name,'username'=>getenv('FLICKARY_TEST_DB_USERNAME')?:'','password'=>getenv('FLICKARY_TEST_DB_PASSWORD')?:'','charset'=>'utf8mb4']);$this->preferences=new UserMediaPreferenceRepository($this->db);$this->users=new UserRepository($this->db);$this->clear();}
    protected function tearDown():void{if(isset($this->db))$this->clear();}

    public function testSchemaIdentityIndexesDefaultsAndCascade():void
    {
        $pdo=$this->db->connection();$names=array_unique(array_column($pdo->query('SHOW INDEX FROM user_media_preferences')->fetchAll(),'Key_name'));
        foreach(['uq_user_media_preferences_identity','idx_preferences_favorite_type','idx_preferences_favorite_anime','idx_preferences_rating_type']as$name)self::assertContains($name,$names);
        $create=strtolower((string)$pdo->query('SHOW CREATE TABLE user_media_preferences')->fetchColumn(1));self::assertStringContainsString('on delete cascade',$create);self::assertMatchesRegularExpression('/`rating` tinyint(?:\(\d+\))? unsigned default null/',$create);self::assertStringContainsString('`is_favorite` tinyint(1) not null default 0',$create);
        $user=$this->users->create('pref.cascade',null);self::assertTrue($this->preferences->create($user,$this->draft(603,'movie',true,null,false)));$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$user]);self::assertSame(0,$this->preferences->countFavorites($user));
    }

    public function testCreateUniqueMappingOwnershipAndTwoUsers():void
    {
        $first=$this->users->create('pref.one',null);$second=$this->users->create('pref.two',null);$draft=$this->draft(603,'movie',true,9,false);
        self::assertTrue($this->preferences->create($first,$draft));self::assertFalse($this->preferences->create($first,$draft));self::assertTrue($this->preferences->create($second,$draft));
        $item=$this->preferences->findForUser($first,'tmdb','movie',603);self::assertTrue($item?->isFavorite);self::assertSame(9,$item?->rating);self::assertFalse($item?->isAnime);
        self::assertNull($this->preferences->findForUser($second,'tmdb','series',603));self::assertFalse($this->preferences->delete($second,'tmdb','series',603));
    }

    public function testLocalUpdatePreservesSnapshotAndFiltersPaginateTransversally():void
    {
        $user=$this->users->create('pref.filters',null);
        $this->preferences->create($user,$this->draft(1,'movie',true,8,true,'Anime Movie'));
        $this->preferences->create($user,$this->draft(2,'series',true,null,true,'Anime Series'));
        $this->preferences->create($user,$this->draft(3,'movie',false,10,false,'Rated Movie'));
        $this->preferences->create($user,$this->draft(4,'series',true,null,null,'Unknown'));
        self::assertTrue($this->preferences->update($user,'tmdb','movie',1,false,9));$updated=$this->preferences->findForUser($user,'tmdb','movie',1);self::assertSame('Anime Movie',$updated?->title);self::assertTrue($updated?->isAnime);self::assertFalse($updated?->isFavorite);self::assertSame(9,$updated?->rating);
        self::assertSame(2,$this->preferences->countFavorites($user));self::assertSame(2,$this->preferences->countRated($user));
        self::assertSame([2],array_map(fn($i)=>$i->sourceId,$this->preferences->paginateFavorites($user,null,true,1,24)->items));
        self::assertEqualsCanonicalizing([1],array_map(fn($i)=>$i->sourceId,$this->preferences->paginateRated($user,null,true,1,24)->items));
        self::assertSame(2,$this->preferences->paginateRated($user,'movie',false,1,1)->total);self::assertSame(2,$this->preferences->paginateRated($user,'movie',false,1,1)->lastPage());
        self::assertSame(2,$this->preferences->paginateFavorites($user,'series',false,1,24)->total);self::assertSame(0,$this->preferences->paginateFavorites($user,'movie',false,1,24)->total);
    }

    private function draft(int$id,string$type,bool$favorite,?int$rating,?bool$anime,string$title='Title'):UserMediaPreferenceDraft{return new UserMediaPreferenceDraft('tmdb',$type,$id,$title,'Original','2025-01-01','/poster.jpg',$anime,$favorite,$rating);}
    private function clear():void{$pdo=$this->db->connection();$pdo->exec('DELETE FROM user_media_preferences');$pdo->exec('DELETE FROM user_external_identities');$pdo->exec('DELETE FROM user_profiles');$pdo->exec('DELETE FROM users');}
}
