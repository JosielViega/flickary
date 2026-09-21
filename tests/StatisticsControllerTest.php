<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\StatisticsController;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Statistics\MonthlyStatistic;
use App\Statistics\PersonalStatistics;
use App\Statistics\StatisticsReader;
use PHPUnit\Framework\TestCase;

final class StatisticsControllerTest extends TestCase
{
    protected function setUp():void{$_SESSION=[];}

    public function testVisitorRedirectsWithoutReadingStatistics():void
    {
        [$controller,$reader]=$this->fixture(false);self::assertSame(303,$controller->index()->status());self::assertSame(0,$reader->calls);
    }

    public function testAuthenticatedPageRendersPartialCoverageAndAccessibleTwelveMonths():void
    {
        [$controller,$reader]=$this->fixture(true);$response=$controller->index();
        self::assertSame(200,$response->status());self::assertSame(1,$reader->calls);
        self::assertStringContainsString('Sua jornada em números',$response->body());
        self::assertStringContainsString('2 h 16 min',$response->body());
        self::assertStringContainsString('1 visualização ainda não possui duração',$response->body());
        self::assertSame(12,substr_count($response->body(),'statistics-chart__month'));
        self::assertStringContainsString('Perfil',$response->body());
        self::assertStringNotContainsString('TMDB_READ_ACCESS_TOKEN',$response->body());
        self::assertSame(5, preg_match_all('/<(?:a|span) class="bottom-nav__item/', $response->body()));
        self::assertStringContainsString('bottom-nav__item is-active" href="/perfil" aria-current="page"', $response->body());
    }

    public function testEmptyJourneyRendersHumanStateAndZeroSections(): void
    {
        [$controller, $reader] = $this->fixture(true);
        $reader->empty = true;
        $body = $controller->index()->body();

        self::assertStringContainsString('Sua jornada ainda está começando.', $body);
        self::assertStringContainsString('href="/buscar"', $body);
        self::assertStringContainsString('0 visualizações', $body);
        self::assertSame(12, substr_count($body, 'statistics-chart__month'));
    }

    private function fixture(bool$authenticated):array
    {
        $session=new Session(false);$auth=new Auth($session);if($authenticated)$auth->login(7);$csrf=new Csrf($session);$reader=new StatisticsMemoryReader();
        return[new StatisticsController(new View(dirname(__DIR__).'/resources/views'),$auth,$csrf,$reader),$reader];
    }
}

final class StatisticsMemoryReader implements StatisticsReader
{
    public int$calls=0; public bool$empty=false;
    public function readForUser(int$userId,string$today):PersonalStatistics
    {
        $this->calls++;$months=[];for($i=1;$i<=12;$i++)$months[]=new MonthlyStatistic('2026-'.str_pad((string)$i,2,'0',STR_PAD_LEFT),'m'.$i,$this->empty?0:($i===12?3:0));
        if($this->empty)return new PersonalStatistics(0,0,0,0,0,0,0,0,0,0,0,$months,['planned'=>0,'watching'=>0,'paused'=>0,'completed'=>0,'dropped'=>0],0,0,0,0,0,0);
        return new PersonalStatistics(3,2,1,1,1,2,1,136,1,3,3,$months,['planned'=>1,'watching'=>0,'paused'=>0,'completed'=>0,'dropped'=>0],1,2,3,1,1,1);
    }
}
