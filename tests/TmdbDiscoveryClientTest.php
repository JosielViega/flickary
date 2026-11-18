<?php

declare(strict_types=1);

namespace Tests;

use App\Integrations\Tmdb\TmdbClient;
use App\Integrations\Tmdb\TmdbMediaNormalizer;
use PHPUnit\Framework\TestCase;

final class TmdbDiscoveryClientTest extends TestCase
{
    public function testDiscoveryEndpointsUseStrictPathsAndExactImportantQueries(): void
    {
        $urls=[];
        $client=new TmdbClient('token',http:static function(string$url)use(&$urls):array{
            $urls[]=$url;
            $isTv=str_contains($url,'/tv/')||str_contains($url,'/trending/tv/')||str_contains($url,'discover/tv');
            $result=$isTv?self::seriesPayload(2):self::moviePayload(1);
            if(str_contains($url,'trending/all')){$result=self::moviePayload(1)+['media_type'=>'movie'];}
            return['status'=>200,'body'=>json_encode(['page'=>1,'total_pages'=>1,'total_results'=>1,'results'=>[$result]],JSON_THROW_ON_ERROR)];
        });
        $client->trendingAll();$client->trendingMovies();$client->trendingSeries();$client->nowPlayingMovies();$client->upcomingMovies();
        $client->popularMovies();$client->popularSeries();$client->topRatedMovies();$client->topRatedSeries();
        $client->bestMoviesOfYear(2028);$client->bestSeriesOfYear(2028);$client->bestAnimeMoviesOfYear(2028);$client->bestAnimeSeriesOfYear(2028);
        self::assertCount(13,$urls);
        self::assertSame('/3/trending/all/day',parse_url($urls[0],PHP_URL_PATH));
        self::assertSame('/3/trending/movie/day',parse_url($urls[1],PHP_URL_PATH));
        self::assertSame('/3/trending/tv/day',parse_url($urls[2],PHP_URL_PATH));
        $this->assertQuery($urls[3],['language'=>'pt-BR','region'=>'BR','page'=>'1']);
        $this->assertQuery($urls[4],['language'=>'pt-BR','region'=>'BR','page'=>'1']);
        $this->assertQuery($urls[9],['language'=>'pt-BR','include_adult'=>'false','primary_release_year'=>'2028','sort_by'=>'vote_average.desc','vote_count_gte'=>'100','page'=>'1','region'=>'BR','include_video'=>'false']);
        $this->assertQuery($urls[10],['language'=>'pt-BR','include_adult'=>'false','first_air_date_year'=>'2028','sort_by'=>'vote_average.desc','vote_count_gte'=>'50','page'=>'1']);
        $animeMovie=[];parse_str((string)parse_url($urls[11],PHP_URL_QUERY),$animeMovie);
        self::assertSame('16',$animeMovie['with_genres']);self::assertSame('ja',$animeMovie['with_original_language']);self::assertSame('25',$animeMovie['vote_count_gte']);
        $animeSeries=[];parse_str((string)parse_url($urls[12],PHP_URL_QUERY),$animeSeries);
        self::assertArrayNotHasKey('region',$animeSeries);self::assertSame('2028',$animeSeries['first_air_date_year']);
    }

    public function testTrendingMixedDropsPeopleAdultsAndInvalidItemsInProviderOrder(): void
    {
        $payload=[
            self::moviePayload(1)+['media_type'=>'movie'],
            self::seriesPayload(2)+['media_type'=>'tv'],
            ['id'=>3,'media_type'=>'person','name'=>'Pessoa'],
            array_replace(self::moviePayload(4),['media_type'=>'movie','adult'=>true]),
            ['media_type'=>'movie','title'=>'Sem ID'],
        ];
        $client=new TmdbClient('token',http:static fn():array=>['status'=>200,'body'=>json_encode(['page'=>1,'total_pages'=>1,'total_results'=>5,'results'=>$payload],JSON_THROW_ON_ERROR)]);
        $results=$client->trendingAll()['results'];
        self::assertSame([1,2],array_map(static fn($media)=>$media->sourceId,$results));
        self::assertSame(['movie','series'],array_map(static fn($media)=>$media->mediaType,$results));
    }

    public function testMixedNormalizerDispatchesOnlyMovieAndTv(): void
    {
        $normalizer=new TmdbMediaNormalizer();
        self::assertSame('movie',$normalizer->mixed(self::moviePayload(1)+['media_type'=>'movie'])?->mediaType);
        self::assertSame('series',$normalizer->mixed(self::seriesPayload(2)+['media_type'=>'tv'])?->mediaType);
        self::assertNull($normalizer->mixed(['id'=>3,'media_type'=>'person','name'=>'Pessoa']));
        self::assertNull($normalizer->mixed(['media_type'=>'movie','title'=>'Inválido']));
    }

    private function assertQuery(string$url,array$expected):void{$actual=[];parse_str((string)parse_url($url,PHP_URL_QUERY),$actual);self::assertSame($expected,$actual);}
    private static function moviePayload(int$id):array{return['id'=>$id,'title'=>'Filme '.$id,'original_title'=>'Movie '.$id,'release_date'=>'2028-01-02','poster_path'=>'/m.jpg','genre_ids'=>[18],'original_language'=>'en','adult'=>false,'vote_average'=>8.0,'vote_count'=>500];}
    private static function seriesPayload(int$id):array{return['id'=>$id,'name'=>'Série '.$id,'original_name'=>'Series '.$id,'first_air_date'=>'2028-02-03','poster_path'=>'/s.jpg','genre_ids'=>[18],'original_language'=>'en','adult'=>false,'vote_average'=>8.0,'vote_count'=>500];}
}
