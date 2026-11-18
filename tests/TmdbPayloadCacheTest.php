<?php

declare(strict_types=1);

namespace Tests;

use App\Integrations\Tmdb\FileTmdbPayloadCache;
use App\Integrations\Tmdb\TmdbCacheProfile;
use App\Integrations\Tmdb\TmdbException;
use PHPUnit\Framework\TestCase;

final class TmdbPayloadCacheTest extends TestCase
{
    private string $directory;
    private int $now = 1000;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/flickary-tmdb-cache-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->directory)) { return; }
        foreach (glob($this->directory . '/*') ?: [] as $file) { if (is_file($file)) { unlink($file); } }
        rmdir($this->directory);
    }

    public function testDetailAndSeasonProfilesUseTheExpectedWindows(): void
    {
        self::assertSame(21600, TmdbCacheProfile::Details->freshSeconds());
        self::assertSame(172800, TmdbCacheProfile::Details->staleSeconds());
        self::assertSame(10800, TmdbCacheProfile::Season->freshSeconds());
        self::assertSame(86400, TmdbCacheProfile::Season->staleSeconds());
    }

    public function testFreshEntryAvoidsSecondUpstreamAndCanonicalizesQuery(): void
    {
        $cache = $this->cache(); $calls = 0;
        $first = $cache->remember('/movie/popular',['b'=>2,'a'=>1],TmdbCacheProfile::Ranking,function () use (&$calls): array { $calls++; return ['page'=>1]; });
        $second = $cache->remember('/movie/popular',['a'=>1,'b'=>2],TmdbCacheProfile::Ranking,function () use (&$calls): array { $calls++; return ['page'=>2]; });
        self::assertSame(['page'=>1],$first); self::assertSame($first,$second); self::assertSame(1,$calls);
        self::assertSame($cache->key('/x',['a'=>1,'b'=>2]),$cache->key('/x',['b'=>2,'a'=>1]));
        self::assertCount(1,glob($this->directory.'/*.json') ?: []);
    }

    public function testExpiredFreshRefreshesAndTransientFailureUsesStale(): void
    {
        $cache=$this->cache(); $calls=0;
        $cache->remember('/trending/all/day',[],TmdbCacheProfile::Trending,function()use(&$calls):array{$calls++;return['value'=>'old'];});
        $this->now += TmdbCacheProfile::Trending->freshSeconds()+1;
        $stale=$cache->remember('/trending/all/day',[],TmdbCacheProfile::Trending,function()use(&$calls):array{$calls++;throw new TmdbException('server_error',500);});
        self::assertSame(['value'=>'old'],$stale); self::assertSame(2,$calls);
    }

    public function testExpiredFreshIsAtomicallyReplacedAfterSuccessfulRefresh(): void
    {
        $cache=$this->cache();$calls=0;
        $loader=function()use(&$calls):array{$calls++;return['version'=>$calls];};
        self::assertSame(['version'=>1],$cache->remember('/movie/popular',[],TmdbCacheProfile::Ranking,$loader));
        $this->now+=TmdbCacheProfile::Ranking->freshSeconds()+1;
        self::assertSame(['version'=>2],$cache->remember('/movie/popular',[],TmdbCacheProfile::Ranking,$loader));
        self::assertSame(['version'=>2],$cache->remember('/movie/popular',[],TmdbCacheProfile::Ranking,$loader));
        self::assertSame(2,$calls);
    }

    public function testUnauthorizedNeverUsesStaleAndExpiredStaleIsRejected(): void
    {
        $cache=$this->cache();
        $cache->remember('/configuration',[],TmdbCacheProfile::Configuration,static fn():array=>['images'=>[]]);
        $this->now += TmdbCacheProfile::Configuration->freshSeconds()+1;
        try {
            $cache->remember('/configuration',[],TmdbCacheProfile::Configuration,static function():array{throw new TmdbException('unauthorized',401);});
            self::fail('Unauthorized should not use stale.');
        } catch (TmdbException $exception) { self::assertSame('unauthorized',$exception->category); }

        $cache=$this->cache();
        $cache->remember('/movie/upcoming',[],TmdbCacheProfile::Schedule,static fn():array=>['page'=>1]);
        $this->now += TmdbCacheProfile::Schedule->staleSeconds()+1;
        try {
            $cache->remember('/movie/upcoming',[],TmdbCacheProfile::Schedule,static function():array{throw new TmdbException('transport');});
            self::fail('Expired stale should not be used.');
        } catch (TmdbException $exception) { self::assertSame('transport',$exception->category); }
    }

    public function testCircuitBreakerStopsCascadingCallsButStillServesCache(): void
    {
        $cache=$this->cache(); $calls=0;
        $cache->remember('/movie/popular',[],TmdbCacheProfile::Ranking,static fn():array=>['cached'=>true]);
        $this->now += TmdbCacheProfile::Ranking->freshSeconds()+1;
        try { $cache->remember('/trending/all/day',[],TmdbCacheProfile::Trending,function()use(&$calls):array{$calls++;throw new TmdbException('transport');}); } catch(TmdbException) {}
        try { $cache->remember('/movie/upcoming',[],TmdbCacheProfile::Schedule,function()use(&$calls):array{$calls++;return['unexpected'=>true];}); } catch(TmdbException) {}
        $cached=$cache->remember('/movie/popular',[],TmdbCacheProfile::Ranking,function()use(&$calls):array{$calls++;return[];});
        self::assertSame(1,$calls); self::assertSame(['cached'=>true],$cached);
    }

    public function testCorruptionTraversalAndSecretSafety(): void
    {
        $cache=$this->cache(); mkdir($this->directory,0775,true);
        $file=$this->directory.'/'.$cache->key('/movie/popular',['query'=>'../../segredo/á']).'.json';
        file_put_contents($file,'{invalid'); $calls=0;
        $payload=$cache->remember('/movie/popular',['query'=>'../../segredo/á'],TmdbCacheProfile::Ranking,function()use(&$calls):array{$calls++;return['ok'=>true];});
        self::assertSame(['ok'=>true],$payload); self::assertSame(1,$calls);
        foreach (glob($this->directory.'/*') ?: [] as $generated) {
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}\.json$/',basename($generated));
            $contents=(string)file_get_contents($generated);
            self::assertStringNotContainsString('Bearer',$contents);
            self::assertStringNotContainsString('TMDB_READ_ACCESS_TOKEN',$contents);
        }
    }

    public function testUnwritableCacheFailsOpen(): void
    {
        $blocker=$this->directory.'.file'; file_put_contents($blocker,'x');
        $cache=new FileTmdbPayloadCache($blocker.'/nested',now:fn():int=>$this->now);
        self::assertSame(['ok'=>true],$cache->remember('/configuration',[],TmdbCacheProfile::Configuration,static fn():array=>['ok'=>true]));
        unlink($blocker);
    }

    private function cache(): FileTmdbPayloadCache
    {
        return new FileTmdbPayloadCache($this->directory,now:fn():int=>$this->now);
    }
}
