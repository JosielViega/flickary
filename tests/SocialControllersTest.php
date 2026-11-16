<?php

declare(strict_types=1);

namespace Tests;

use App\Authentication\UsernamePolicy;
use App\Controllers\FollowController;
use App\Controllers\PublicProfileController;
use App\Controllers\UserSearchController;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Core\PublicUrl;
use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageConfiguration;
use App\Integrations\Tmdb\TmdbMediaDetails;
use App\Integrations\Tmdb\TmdbSeasonDetails;
use App\Social\FollowStore;
use App\Social\PublicFavorite;
use App\Social\PublicProfile;
use App\Social\PublicProfilePage;
use App\Social\PublicProfileReader;
use App\Social\PublicProfileStatistics;
use PHPUnit\Framework\TestCase;

final class SocialControllersTest extends TestCase
{
    private array $previousSession;
    private bool $sessionExisted;
    private Session $session;
    private Auth $auth;
    private Csrf $csrf;
    private SocialProfileReaderFake $profiles;
    private FollowStoreFake $follows;

    protected function setUp(): void
    {
        $this->sessionExisted=isset($_SESSION); $this->previousSession=$_SESSION??[]; $_SESSION=[];
        $this->session=new Session(false); $this->auth=new Auth($this->session); $this->csrf=new Csrf($this->session);
        $this->profiles=new SocialProfileReaderFake(); $this->follows=new FollowStoreFake();
    }

    protected function tearDown(): void
    {
        if($this->sessionExisted){$_SESSION=$this->previousSession;}else{unset($_SESSION);}
    }

    public function testPublicProfileEscapesIdentityAndShowsOnlyAllowedAggregates(): void
    {
        $this->profiles->profile=$this->profile(false,'<script>alert(1)</script>','<img onerror=alert(1)>');
        $this->profiles->favorites=[new PublicFavorite('movie',603,'Matrix <script>',null,'1999-03-31',null,false,9)];
        $response=$this->publicController()->show('social.user');
        self::assertSame(200,$response->status());
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;',$response->body());
        self::assertStringContainsString('&lt;img onerror=alert(1)&gt;',$response->body());
        self::assertStringContainsString('Na coleção',$response->body());
        self::assertStringContainsString('Matrix &lt;script&gt;',$response->body());
        self::assertStringNotContainsString('<script>alert(1)</script>',$response->body());
        self::assertStringNotContainsString('scheduled_on',$response->body());
    }

    public function testPrivateProfileHidesContentEvenFromFollower(): void
    {
        $this->profiles->profile=$this->profile(true);
        $this->auth->login(22); $this->follows->following=true;
        $response=$this->publicController()->show('social.user');
        self::assertSame(200,$response->status());
        self::assertStringContainsString('Perfil privado',$response->body());
        self::assertStringContainsString('não libera o conteúdo privado',$response->body());
        self::assertStringNotContainsString('Na coleção',$response->body());
        self::assertSame(0,$this->profiles->statisticsCalls);
        self::assertSame(0,$this->profiles->favoriteCalls);
    }

    public function testPrivateOwnerSeesFullPreviewAndEditLink(): void
    {
        $this->profiles->profile=$this->profile(true); $this->auth->login(10);
        $response=$this->publicController()->show('social.user');
        self::assertStringContainsString('Seu perfil está privado',$response->body());
        self::assertStringContainsString('href="/perfil"',$response->body());
        self::assertStringContainsString('Na coleção',$response->body());
        self::assertStringNotContainsString('>Seguir<',$response->body());
    }

    public function testPublicProfileHasCanonicalOpenGraphAndShareActions(): void
    {
        $this->profiles->profile = $this->profile(false, 'Social User', "Cinema\n  & séries");
        $response = $this->publicController()->show('social.user');

        self::assertStringContainsString('<link rel="canonical" href="https://app.example.test/base/u/social.user">', $response->body());
        self::assertStringContainsString('<meta property="og:title" content="Social User (@social.user) · Flickary">', $response->body());
        self::assertStringContainsString('<meta property="og:description" content="Cinema &amp; séries">', $response->body());
        self::assertStringContainsString('<meta property="og:image" content="https://app.example.test/base/assets/images/flickary-share.png">', $response->body());
        self::assertStringContainsString('data-share-url="https://app.example.test/base/u/social.user"', $response->body());
        self::assertStringContainsString('<script src="/assets/js/share.js" defer></script>', $response->body());
    }

    public function testPrivateProfileMetadataIsStableAndDoesNotExposeBioOrViewerContent(): void
    {
        $this->profiles->profile = $this->profile(true, 'Private User', 'BIO SECRET 123');
        $visitor = $this->publicController()->show('social.user')->body();
        $visitorHead = strstr($visitor, '</head>', true);

        $this->auth->login(10);
        $owner = $this->publicController()->show('social.user')->body();
        $ownerHead = strstr($owner, '</head>', true);

        self::assertSame($visitorHead, $ownerHead);
        self::assertStringContainsString('Perfil privado de @social.user no Flickary.', $visitorHead);
        self::assertStringNotContainsString('BIO SECRET 123', $visitorHead);
        self::assertStringNotContainsString('Na coleção', $visitorHead);
    }

    public function testProfileMetadataPrefersCoverThenAvatar(): void
    {
        $this->profiles->profile = new PublicProfile(10, 'social.user', 'Social User', null,
            'https://images.example/avatar.jpg', 'https://images.example/cover.jpg', false, '2026-01-02 10:00:00');
        self::assertStringContainsString('content="https://images.example/cover.jpg"', $this->publicController()->show('social.user')->body());

        $this->profiles->profile = new PublicProfile(10, 'social.user', 'Social User', null,
            'https://images.example/avatar.jpg', null, false, '2026-01-02 10:00:00');
        self::assertStringContainsString('content="https://images.example/avatar.jpg"', $this->publicController()->show('social.user')->body());
    }

    public function testInvalidAndMissingUsernameReturnNotFoundWithoutReaderLookup(): void
    {
        self::assertSame(404,$this->publicController()->show('../bad')->status());
        self::assertSame(0,$this->profiles->findCalls);
        self::assertSame(404,$this->publicController()->show('missing.user')->status());
        self::assertSame(1,$this->profiles->findCalls);
    }

    public function testFollowRequiresAuthCsrfPreventsSelfAndUsesRouteTargetIdempotently(): void
    {
        $this->profiles->profile=$this->profile(false);
        $controller=$this->followController();
        self::assertSame(303,$controller->follow(new Request(),'social.user')->status());
        $this->auth->login(22);
        self::assertSame(403,$controller->follow(new Request(),'social.user')->status());
        $request=new Request(parsedBody:['_token'=>$this->csrf->token(),'target_user_id'=>'999']);
        self::assertSame(303,$controller->follow($request,'social.user')->status());
        self::assertSame([[22,10]],$this->follows->followCalls);
        self::assertSame(303,$controller->follow($request,'social.user')->status());
        self::assertSame(1,count($this->follows->relations));
        $this->auth->logout(); $this->auth->login(10);
        self::assertSame(422,$controller->follow($request,'social.user')->status());
    }

    public function testUnfollowIsIdempotentAndUsesPrg(): void
    {
        $this->profiles->profile=$this->profile(false); $this->auth->login(22);
        $request=new Request(parsedBody:['_token'=>$this->csrf->token()]);
        $response=$this->followController()->unfollow($request,'social.user');
        self::assertSame(303,$response->status()); self::assertSame('/u/social.user',$response->headers()['Location']);
    }

    public function testUserSearchValidatesAndEscapesResults(): void
    {
        $this->profiles->profile=$this->profile(false,'<b>Name</b>');
        $controller=new UserSearchController($this->view(),$this->auth,$this->csrf,$this->profiles);
        self::assertSame(422,$controller->index(new Request(queryParams:['q'=>'x']))->status());
        $response=$controller->index(new Request(queryParams:['q'=>'social']));
        self::assertSame(200,$response->status());
        self::assertStringContainsString('&lt;b&gt;Name&lt;/b&gt;',$response->body());
        self::assertStringContainsString('Perfil público',$response->body());
    }

    private function publicController(): PublicProfileController
    {
        return new PublicProfileController($this->view(),$this->auth,$this->csrf,$this->session,new UsernamePolicy(),$this->profiles,$this->follows,new OfflineTmdbCatalog(),new PublicUrl('https://app.example.test/base/'));
    }
    private function followController(): FollowController
    {
        return new FollowController($this->view(),$this->auth,$this->csrf,$this->session,new UsernamePolicy(),$this->profiles,$this->follows);
    }
    private function view(): View { return new View(dirname(__DIR__).'/resources/views'); }
    private function profile(bool $private,string $name='Social User',?string $bio='Cinema & séries'): PublicProfile
    { return new PublicProfile(10,'social.user',$name,$bio,null,null,$private,'2026-01-02 10:00:00'); }
}

final class SocialProfileReaderFake implements PublicProfileReader
{
    public ?PublicProfile $profile=null; public array $favorites=[]; public int $findCalls=0; public int $statisticsCalls=0; public int $favoriteCalls=0;
    public function findByUsername(string $username):?PublicProfile{$this->findCalls++;return $this->profile?->username===$username?$this->profile:null;}
    public function statisticsForUser(int $userId):PublicProfileStatistics{$this->statisticsCalls++;return new PublicProfileStatistics(4,1,2,8.5,7,320,1);}
    public function recentFavorites(int $userId,int $limit=6):array{$this->favoriteCalls++;return array_slice($this->favorites,0,$limit);}
    public function search(string $query,int $page,int $perPage):PublicProfilePage{return new PublicProfilePage($this->profile===null?[]:[$this->profile],$this->profile===null?0:1,$page,$perPage);}
}

final class FollowStoreFake implements FollowStore
{
    public bool $following=false; public array $followCalls=[]; public array $relations=[];
    public function follow(int $followerUserId,int $followedUserId):void{$this->followCalls[]=[$followerUserId,$followedUserId];$this->relations[$followerUserId.':'.$followedUserId]=true;}
    public function unfollow(int $followerUserId,int $followedUserId):void{unset($this->relations[$followerUserId.':'.$followedUserId]);}
    public function isFollowing(int $followerUserId,int $followedUserId):bool{return $this->following||isset($this->relations[$followerUserId.':'.$followedUserId]);}
    public function countFollowers(int $userId):int{return 3;} public function countFollowing(int $userId):int{return 2;}
    public function paginateFollowers(int $userId,int $page,int $perPage):PublicProfilePage{return new PublicProfilePage([],0,$page,$perPage);}
    public function paginateFollowing(int $userId,int $page,int $perPage):PublicProfilePage{return new PublicProfilePage([],0,$page,$perPage);}
}

final class OfflineTmdbCatalog implements TmdbCatalog
{
    public function configured():bool{return false;} public function configuration():TmdbImageConfiguration{throw new TmdbException('not_configured');}
    public function movieDetails(int $id):TmdbMediaDetails{throw new TmdbException('not_configured');} public function seriesDetails(int $id):TmdbMediaDetails{throw new TmdbException('not_configured');}
    public function seasonDetails(int $seriesId,int $seasonNumber):TmdbSeasonDetails{throw new TmdbException('not_configured');}
    public function searchMovies(string $query,int $page=1):array{return ['page'=>1,'total_pages'=>0,'total_results'=>0,'results'=>[]];}
    public function searchSeries(string $query,int $page=1):array{return ['page'=>1,'total_pages'=>0,'total_results'=>0,'results'=>[]];}
    public function discoverAnimeMovies(int $page=1):array{return ['page'=>1,'total_pages'=>0,'total_results'=>0,'results'=>[]];}
    public function discoverAnimeSeries(int $page=1):array{return ['page'=>1,'total_pages'=>0,'total_results'=>0,'results'=>[]];}
}
