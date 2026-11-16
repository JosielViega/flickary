<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\SocialFeedController;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageConfiguration;
use App\Social\FollowStore;
use App\Social\PublicProfilePage;
use App\Social\SocialActivity;
use App\Social\SocialActivityLikeResult;
use App\Social\SocialActivityLikeStore;
use App\Social\SocialFeedPage;
use App\Social\SocialFeedReader;
use PHPUnit\Framework\TestCase;

final class SocialFeedControllerTest extends TestCase
{
    private bool $sessionExisted;
    private array $previousSession;
    private Session $session;
    private Auth $auth;

    protected function setUp(): void
    {
        $this->sessionExisted = isset($_SESSION);
        $this->previousSession = $_SESSION ?? [];
        $_SESSION = [];
        $this->session = new Session(false);
        $this->auth = new Auth($this->session);
    }

    protected function tearDown(): void
    {
        if ($this->sessionExisted) {
            $_SESSION = $this->previousSession;
        } else {
            unset($_SESSION);
        }
    }

    public function testVisitorIsRedirectedWithSeeOther(): void
    {
        $response = $this->controller(new SocialFeedPage([], 0, 1, 30))->index(new Request());
        self::assertSame(303, $response->status());
        self::assertSame('/login', $response->headers()['Location']);
    }

    public function testEmptyStatesDistinguishNoFollowsFromNoSharedActivity(): void
    {
        $this->auth->login(7);
        $withoutFollows = $this->controller(new SocialFeedPage([], 0, 1, 30), 0)->index(new Request());
        self::assertStringContainsString('Seu Social ainda está vazio', $withoutFollows->body());
        self::assertStringContainsString('href="/usuarios"', $withoutFollows->body());

        $withFollows = $this->controller(new SocialFeedPage([], 0, 1, 30), 2)->index(new Request());
        self::assertStringContainsString('Ainda não há novas atividades compartilhadas', $withFollows->body());
        self::assertStringNotContainsString('desativou compartilhamento', $withFollows->body());
    }

    public function testRendersLocalMovieEpisodeAnimeAndEscapesSnapshotsWithoutDetailCalls(): void
    {
        $this->auth->login(7);
        $movie = $this->activity(31, 'movie', '<script>Matrix</script>', null, null, null, true, 3, true);
        $episode = $this->activity(32, 'episode', 'Breaking Bad', '<img src=x onerror=alert(1)>', 1, 1, false);
        $tmdb = new SocialFeedTmdbFake();
        $response = $this->controller(new SocialFeedPage([$episode, $movie], 31, 1, 30), 1, $tmdb)
            ->index(new Request(queryParams: ['page' => '1']));

        self::assertSame(200, $response->status());
        self::assertSame(1, $tmdb->configurationCalls);
        self::assertSame(0, $tmdb->detailCalls);
        self::assertStringContainsString('&lt;script&gt;Matrix&lt;/script&gt;', $response->body());
        self::assertStringNotContainsString('<script>Matrix</script>', $response->body());
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $response->body());
        self::assertStringContainsString('Anime · Filme · 1999', $response->body());
        self::assertStringContainsString('T1E1', $response->body());
        self::assertStringContainsString('Assistiu em 21/05/1999', $response->body());
        self::assertStringContainsString('href="/filmes/603"', $response->body());
        self::assertStringContainsString('href="/series/603/temporadas/1"', $response->body());
        self::assertStringContainsString('href="/u/actor.user"', $response->body());
        self::assertStringContainsString('href="/social" aria-current="page"', $response->body());
        self::assertStringContainsString('Página 1 de 2', $response->body());
        self::assertStringContainsString('action="/social/atividades/31/descurtir"', $response->body());
        self::assertStringContainsString('aria-pressed="true"', $response->body());
        self::assertStringContainsString('Curtido · 3', $response->body());
        self::assertStringContainsString('action="/social/atividades/32/curtir"', $response->body());
        self::assertStringContainsString('Curtir · 0', $response->body());
        self::assertSame(5, preg_match_all('/<(?:a|span) class="bottom-nav__item/', $response->body()));
        self::assertStringContainsString('bottom-nav__item is-active', $response->body());
    }

    public function testTmdbConfigurationFailureKeepsFeedAvailableWithPosterFallback(): void
    {
        $this->auth->login(7);
        $tmdb = new SocialFeedTmdbFake(true);
        $response = $this->controller(new SocialFeedPage([$this->activity(1)], 1, 1, 30), 1, $tmdb)
            ->index(new Request());

        self::assertSame(200, $response->status());
        self::assertSame(1, $tmdb->configurationCalls);
        self::assertSame(0, $tmdb->detailCalls);
        self::assertStringContainsString('Matrix', $response->body());
        self::assertStringNotContainsString('image.tmdb.test', $response->body());
    }

    public function testLikeRequiresAuthCsrfAndValidInternalId(): void
    {
        $likes = new SocialActivityLikeStoreFake();
        $controller = $this->controller(new SocialFeedPage([], 0, 1, 30), likes: $likes);
        self::assertSame(303, $controller->like(new Request(), '31')->status());

        $this->auth->login(7);
        self::assertSame(403, $controller->like(new Request(), '31')->status());
        $request = new Request(parsedBody: ['_token' => (new Csrf($this->session))->token()]);
        self::assertSame(404, $controller->like($request, '0')->status());
        self::assertSame(404, $controller->like($request, '12x')->status());
        self::assertSame([], $likes->likeCalls);
    }

    public function testLikeHidesInvisibleActivityAndUsesPrgWithValidatedPage(): void
    {
        $this->auth->login(7);
        $likes = new SocialActivityLikeStoreFake(SocialActivityLikeResult::Hidden);
        $controller = $this->controller(new SocialFeedPage([], 0, 1, 30), likes: $likes);
        $csrf = (new Csrf($this->session))->token();
        self::assertSame(404, $controller->like(new Request(parsedBody: ['_token' => $csrf]), '31')->status());

        $likes->result = SocialActivityLikeResult::Liked;
        $response = $controller->like(new Request(parsedBody: ['_token' => $csrf, 'page' => '2']), '31');
        self::assertSame(303, $response->status());
        self::assertSame('/social?page=2', $response->headers()['Location']);

        $likes->result = SocialActivityLikeResult::AlreadyLiked;
        $duplicate = $controller->like(new Request(parsedBody: ['_token' => $csrf, 'page' => 'bad']), '31');
        self::assertSame('/social', $duplicate->headers()['Location']);
        self::assertSame([[7, 31], [7, 31], [7, 31]], $likes->likeCalls);
    }

    public function testUnlikeIsIdempotentAndCanRemoveAfterVisibilityIsLost(): void
    {
        $likes = new SocialActivityLikeStoreFake();
        $controller = $this->controller(new SocialFeedPage([], 0, 1, 30), likes: $likes);
        self::assertSame(303, $controller->unlike(new Request(), '31')->status());

        $this->auth->login(7);
        $csrf = (new Csrf($this->session))->token();
        self::assertSame(403, $controller->unlike(new Request(), '31')->status());
        $response = $controller->unlike(new Request(parsedBody: ['_token' => $csrf, 'page' => '10001']), '31');
        self::assertSame(303, $response->status());
        self::assertSame('/social', $response->headers()['Location']);
        self::assertSame([[7, 31]], $likes->unlikeCalls);
    }

    private function controller(
        SocialFeedPage $page,
        int $following = 0,
        ?SocialFeedTmdbFake $tmdb = null,
        ?SocialActivityLikeStoreFake $likes = null,
    ): SocialFeedController
    {
        $reader = new class($page) implements SocialFeedReader {
            public function __construct(private readonly SocialFeedPage $page) {}
            public function paginateForViewer(int $viewerUserId, int $page, int $perPage): SocialFeedPage { return $this->page; }
        };
        $follows = new class($following) implements FollowStore {
            public function __construct(private readonly int $following) {}
            public function follow(int $followerUserId,int $followedUserId):void{}
            public function unfollow(int $followerUserId,int $followedUserId):void{}
            public function isFollowing(int $followerUserId,int $followedUserId):bool{return false;}
            public function countFollowers(int $userId):int{return 0;}
            public function countFollowing(int $userId):int{return $this->following;}
            public function paginateFollowers(int $userId,int $page,int $perPage):PublicProfilePage{return new PublicProfilePage([],0,$page,$perPage);}
            public function paginateFollowing(int $userId,int $page,int $perPage):PublicProfilePage{return new PublicProfilePage([],0,$page,$perPage);}
        };

        return new SocialFeedController(
            new View(dirname(__DIR__) . '/resources/views'),
            $this->auth,
            new Csrf($this->session),
            $reader,
            $follows,
            $likes ?? new SocialActivityLikeStoreFake(),
            $tmdb ?? new SocialFeedTmdbFake(),
        );
    }

    private function activity(
        int $id,
        string $type = 'movie',
        string $title = 'Matrix',
        ?string $episodeTitle = null,
        ?int $season = null,
        ?int $episode = null,
        ?bool $anime = false,
        int $likeCount = 0,
        bool $viewerLiked = false,
    ): SocialActivity {
        return new SocialActivity(
            $id, 20, 'actor.user', '<Actor>', 'https://example.test/avatar.jpg', 'tmdb', $type, 603,
            $season, $episode, $title, $episodeTitle, '1999-03-31', '/poster.jpg', $anime,
            '1999-05-21', '2026-11-16 12:00:00', $likeCount, $viewerLiked, 0,
        );
    }
}

final class SocialActivityLikeStoreFake implements SocialActivityLikeStore
{
    public array $likeCalls = [];
    public array $unlikeCalls = [];

    public function __construct(public SocialActivityLikeResult $result = SocialActivityLikeResult::Liked) {}
    public function likeVisibleActivity(int $viewerUserId, int $historyId): SocialActivityLikeResult
    {
        $this->likeCalls[] = [$viewerUserId, $historyId];
        return $this->result;
    }
    public function unlike(int $viewerUserId, int $historyId): void
    {
        $this->unlikeCalls[] = [$viewerUserId, $historyId];
    }
}

final class SocialFeedTmdbFake implements TmdbCatalog
{
    public int $configurationCalls = 0;
    public int $detailCalls = 0;

    public function __construct(private readonly bool $offline = false) {}
    public function configured(): bool { return true; }
    public function configuration(): TmdbImageConfiguration
    {
        $this->configurationCalls++;
        if ($this->offline) { throw new TmdbException('network_error'); }
        return new TmdbImageConfiguration('https://image.tmdb.test/t/p/', ['w342'], ['w780']);
    }
    public function movieDetails(int $id): \App\Integrations\Tmdb\TmdbMediaDetails { $this->detailCalls++; throw new TmdbException('unexpected'); }
    public function seriesDetails(int $id): \App\Integrations\Tmdb\TmdbMediaDetails { $this->detailCalls++; throw new TmdbException('unexpected'); }
    public function seasonDetails(int $seriesId,int $seasonNumber): \App\Integrations\Tmdb\TmdbSeasonDetails { $this->detailCalls++; throw new TmdbException('unexpected'); }
    public function searchMovies(string $query,int $page=1):array{return [];}
    public function searchSeries(string $query,int $page=1):array{return [];}
    public function discoverAnimeMovies(int $page=1):array{return [];}
    public function discoverAnimeSeries(int $page=1):array{return [];}
}
