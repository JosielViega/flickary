<?php

declare(strict_types=1);

namespace Tests;

use App\Discovery\ContentDiscoveryService;
use App\Integrations\Tmdb\FileTmdbPayloadCache;
use App\Integrations\Tmdb\TmdbClient;
use PHPUnit\Framework\TestCase;

final class TmdbDiscoveryCacheIntegrationTest extends TestCase
{
    private string $directory;

    protected function setUp():void{$this->directory=sys_get_temp_dir().'/flickary-discovery-integration-'.bin2hex(random_bytes(6));}
    protected function tearDown():void{if(!is_dir($this->directory))return;foreach(glob($this->directory.'/*')?:[]as$file){if(is_file($file))unlink($file);}rmdir($this->directory);}

    public function testColdWarmAndTransientStaleRequestCounts(): void
    {
        $now=1000;$calls=0;$offline=false;
        $http=static function(string$url)use(&$calls,&$offline):array{
            $calls++; if($offline)return['transport_error'=>true];
            if(str_contains($url,'/configuration'))return['status'=>200,'body'=>json_encode(['images'=>['secure_base_url'=>'https://image.tmdb.test/t/p/','poster_sizes'=>['w342'],'backdrop_sizes'=>['w780']]],JSON_THROW_ON_ERROR)];
            return['status'=>200,'body'=>json_encode(self::collectionPayload($url),JSON_THROW_ON_ERROR)];
        };
        $cache=new FileTmdbPayloadCache($this->directory,now:static function()use(&$now):int{return$now;});
        $client=new TmdbClient('test-token',http:$http,cache:$cache);
        $service=new ContentDiscoveryService($client,$client,now:static fn()=>new \DateTimeImmutable('2028-01-01'));
        self::assertTrue($service->page('todos')->available()); self::assertSame(10,$calls);
        self::assertTrue($service->page('todos')->available()); self::assertSame(10,$calls);
        $now+=3601;$offline=true;
        self::assertTrue($service->page('todos')->available()); self::assertSame(11,$calls);
    }

    public function testOfflineWithoutCacheFailsFastAndServiceDegradesSafely(): void
    {
        $calls=0;
        $client=new TmdbClient('test-token',http:static function()use(&$calls):array{$calls++;return['transport_error'=>true];},cache:new FileTmdbPayloadCache($this->directory));
        $page=(new ContentDiscoveryService($client,$client))->page('todos');
        self::assertFalse($page->available()); self::assertSame(1,$calls);
    }

    private static function collectionPayload(string$url):array
    {
        $tv=str_contains($url,'/tv/')||str_contains($url,'/trending/tv/')||str_contains($url,'discover/tv');
        $anime=str_contains($url,'with_genres=16');
        $item=$tv
            ? ['id'=>2,'name'=>'Série','original_name'=>'Series','first_air_date'=>'2028-01-01']
            : ['id'=>1,'title'=>'Filme','original_title'=>'Movie','release_date'=>'2028-01-01'];
        $item+=['poster_path'=>'/poster.jpg','genre_ids'=>$anime?[16]:[18],'original_language'=>$anime?'ja':'en','adult'=>false,'vote_average'=>8.0,'vote_count'=>500];
        if(str_contains($url,'trending/all'))$item['media_type']='movie';
        return['page'=>1,'total_pages'=>1,'total_results'=>1,'results'=>[$item]];
    }
}
