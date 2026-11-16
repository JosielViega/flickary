<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\ProfileCommentsController;
use App\Controllers\SocialActivityController;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageConfiguration;
use App\Social\SocialActivity;
use App\Social\SocialActivityAccess;
use App\Social\SocialActivityComment;
use App\Social\SocialActivityCommentCreateResult;
use App\Social\SocialActivityCommentPage;
use App\Social\SocialActivityCommentReader;
use App\Social\SocialActivityCommentStore;
use App\Social\SocialActivityDetail;
use App\Social\SocialCommentBody;
use App\Social\SocialOwnComment;
use App\Social\SocialOwnCommentPage;
use PHPUnit\Framework\TestCase;

final class SocialActivityControllerTest extends TestCase
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
        if ($this->sessionExisted) { $_SESSION = $this->previousSession; } else { unset($_SESSION); }
    }

    public function testPrivateEndpointsRedirectVisitors(): void
    {
        [$controller, $reader] = $this->controller();
        self::assertSame('/login', $controller->show(new Request(), '31')->headers()['Location']);
        self::assertSame('/login', $controller->createComment(new Request(), '31')->headers()['Location']);
        self::assertSame('/login', $controller->deleteComment(new Request(), '7')->headers()['Location']);
        $profile = new ProfileCommentsController($this->view(), $this->auth, new Csrf($this->session), $reader);
        self::assertSame('/login', $profile->index(new Request())->headers()['Location']);
    }

    public function testDetailEscapesPlainTextAndDoesNotFetchTmdbDetails(): void
    {
        $this->auth->login(7);
        $reader = new SocialCommentFake($this->detail());
        $reader->page = new SocialActivityCommentPage([
            new SocialActivityComment(9, 31, 7, 'author', '<Author>', null, "Hello\n<script>alert(1)</script>", '2026-11-16 10:00:00'),
        ], 1, 1, 30);
        $tmdb = new SocialCommentTmdbFake();
        $controller = $this->controller($reader, $tmdb)[0];
        $response = $controller->show(new Request(), '31');
        self::assertSame(200, $response->status());
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $response->body());
        self::assertStringNotContainsString('<script>alert(1)</script>', $response->body());
        self::assertStringContainsString('action="/social/comentarios/9/remover"', $response->body());
        self::assertSame(1, $tmdb->configurationCalls);
        self::assertSame(0, $tmdb->detailCalls);
    }

    public function testTmdbConfigurationFailureKeepsLocalDetailAvailable(): void
    {
        $this->auth->login(7);
        $reader = new SocialCommentFake($this->detail());
        $tmdb = new SocialCommentTmdbFake(true);
        $response = $this->controller($reader, $tmdb)[0]->show(new Request(), '31');
        self::assertSame(200, $response->status());
        self::assertStringContainsString('&lt;Matrix&gt;', $response->body());
        self::assertStringNotContainsString('image.tmdb.test', $response->body());
        self::assertSame(1, $tmdb->configurationCalls);
        self::assertSame(0, $tmdb->detailCalls);
    }

    public function testCreationValidatesCsrfBodyVisibilityAndUsesPrg(): void
    {
        $this->auth->login(7);
        $reader = new SocialCommentFake($this->detail());
        [$controller] = $this->controller($reader);
        self::assertSame(403, $controller->createComment(new Request(), '31')->status());
        $csrf = (new Csrf($this->session))->token();
        $invalid = $controller->createComment(new Request(parsedBody: ['_token' => $csrf, 'body' => '<b>']), '31');
        self::assertSame(303, $invalid->status());
        self::assertSame('/social/atividades/31', $invalid->headers()['Location']);
        self::assertSame('<b>', $reader->createdBody);

        $reader->createResult = SocialActivityCommentCreateResult::Hidden;
        self::assertSame(404, $controller->createComment(new Request(parsedBody: ['_token' => $csrf, 'body' => 'hidden']), '31')->status());
        $invalidBody = $controller->createComment(new Request(parsedBody: ['_token' => $csrf, 'body' => '']), '31');
        self::assertSame(422, $invalidBody->status());
        self::assertStringContainsString('aria-invalid="true"', $invalidBody->body());
    }

    public function testDeletionUsesAllowlistedContextsAndHidesUnauthorized(): void
    {
        $this->auth->login(7);
        $reader = new SocialCommentFake($this->detail());
        [$controller] = $this->controller($reader);
        $csrf = (new Csrf($this->session))->token();
        self::assertSame(403, $controller->deleteComment(new Request(), '9')->status());
        $reader->deleteHistoryId = null;
        self::assertSame(404, $controller->deleteComment(new Request(parsedBody: ['_token' => $csrf]), '9')->status());
        $reader->deleteHistoryId = 31;
        $profile = $controller->deleteComment(new Request(parsedBody: ['_token' => $csrf, 'context' => 'profile']), '9');
        self::assertSame('/perfil/comentarios', $profile->headers()['Location']);
        $unsafe = $controller->deleteComment(new Request(parsedBody: ['_token' => $csrf, 'context' => 'https://evil.test']), '9');
        self::assertSame('/social/atividades/31', $unsafe->headers()['Location']);
    }

    private function controller(?SocialCommentFake $fake = null, ?SocialCommentTmdbFake $tmdb = null): array
    {
        $fake ??= new SocialCommentFake($this->detail());
        return [new SocialActivityController($this->view(), $this->auth, new Csrf($this->session), $fake, $fake, $tmdb ?? new SocialCommentTmdbFake()), $fake];
    }

    private function view(): View { return new View(dirname(__DIR__) . '/resources/views'); }

    private function detail(): SocialActivityDetail
    {
        return new SocialActivityDetail(new SocialActivity(
            31, 20, 'actor', 'Actor', null, 'tmdb', 'movie', 603, null, null, '<Matrix>', null,
            '1999-03-31', '/poster.jpg', false, '2026-11-16', '2026-11-16 09:00:00', 2, false, 1,
        ), SocialActivityAccess::Follower, true);
    }
}

final class SocialCommentFake implements SocialActivityCommentReader, SocialActivityCommentStore
{
    public SocialActivityCommentPage $page;
    public SocialOwnCommentPage $ownPage;
    public SocialActivityCommentCreateResult $createResult = SocialActivityCommentCreateResult::Created;
    public ?int $deleteHistoryId = 31;
    public ?string $createdBody = null;

    public function __construct(public ?SocialActivityDetail $detail)
    {
        $this->page = new SocialActivityCommentPage([], 0, 1, 30);
        $this->ownPage = new SocialOwnCommentPage([], 0, 1, 30);
    }
    public function detailForViewer(int $viewerUserId,int $historyId):?SocialActivityDetail{return $this->detail;}
    public function paginateForActivity(int $historyId,int $page,int $perPage):SocialActivityCommentPage{return $this->page;}
    public function paginateForAuthor(int $userId,int $page,int $perPage):SocialOwnCommentPage{return $this->ownPage;}
    public function createAuthorized(int $viewerUserId,int $historyId,SocialCommentBody $body):SocialActivityCommentCreateResult{$this->createdBody=$body->value;return $this->createResult;}
    public function deleteAuthorized(int $viewerUserId,int $commentId):?int{return $this->deleteHistoryId;}
}

final class SocialCommentTmdbFake implements TmdbCatalog
{
    public int $configurationCalls=0;
    public int $detailCalls=0;
    public function __construct(private readonly bool $offline=false){}
    public function configured():bool{return true;}
    public function configuration():TmdbImageConfiguration{$this->configurationCalls++;if($this->offline){throw new TmdbException('network_error');}return new TmdbImageConfiguration('https://image.tmdb.test/t/p/',['w342'],['w780']);}
    public function movieDetails(int$id):\App\Integrations\Tmdb\TmdbMediaDetails{$this->detailCalls++;throw new TmdbException('unexpected');}
    public function seriesDetails(int$id):\App\Integrations\Tmdb\TmdbMediaDetails{$this->detailCalls++;throw new TmdbException('unexpected');}
    public function seasonDetails(int$seriesId,int$seasonNumber):\App\Integrations\Tmdb\TmdbSeasonDetails{$this->detailCalls++;throw new TmdbException('unexpected');}
    public function searchMovies(string$query,int$page=1):array{return[];}public function searchSeries(string$query,int$page=1):array{return[];}
    public function discoverAnimeMovies(int$page=1):array{return[];}public function discoverAnimeSeries(int$page=1):array{return[];}
}
