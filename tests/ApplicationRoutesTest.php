<?php

declare(strict_types=1);

namespace Tests;

use App\Authentication\AccountCreationResult;
use App\Authentication\AccountCreator;
use App\Authentication\ConnectedProviderReader;
use App\Authentication\ExternalIdentity;
use App\Authentication\ExternalIdentityFinder;
use App\Authentication\ExternalIdentityLinker;
use App\Authentication\FacebookIdentityProvider;
use App\Authentication\FacebookOAuthState;
use App\Authentication\GoogleIdentityVerifier;
use App\Authentication\PendingExternalOnboarding;
use App\Authentication\UsernamePolicy;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Profiles\ProfileStore;
use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbImageConfiguration;
use PHPUnit\Framework\TestCase;

final class ApplicationRoutesTest extends TestCase
{
    private bool $sessionExisted;
    private array $previousSession;

    protected function setUp(): void
    {
        $this->sessionExisted = isset($_SESSION);
        $this->previousSession = $_SESSION ?? [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if ($this->sessionExisted) {
            $_SESSION = $this->previousSession;
        } else {
            unset($_SESSION);
        }
    }

    public function testHomeRepresentsInitialFlickaryBaseline(): void
    {
        $request = $this->request('GET', '/');
        $response = $this->router($request)->dispatch($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('<html lang="pt-BR">', $response->body());
        self::assertStringContainsString('Flickary', $response->body());
        self::assertStringContainsString('Sua história', $response->body());
        self::assertStringContainsString('Passado · Presente · Futuro', $response->body());
        self::assertStringContainsString('method="get" action="/buscar"', $response->body());
        self::assertStringNotContainsString('href="#"', $response->body());
        self::assertStringContainsString('href="/login"', $response->body());
    }

    public function testHealthRemainsSmallAndSafe(): void
    {
        $request = $this->request('GET', '/health');
        $response = $this->router($request)->dispatch($request);

        self::assertSame(200, $response->status());
        self::assertSame('{"status":"ok"}', $response->body());
        self::assertSame('application/json; charset=UTF-8', $response->headers()['Content-Type']);
    }

    public function testDemonstrationPostRouteNoLongerExists(): void
    {
        $request = $this->request('POST', '/example');
        $response = $this->router($request)->dispatch($request);

        self::assertSame(404, $response->status());
    }

    public function testNotFoundPageUsesPortugueseFlickaryExperience(): void
    {
        $request = $this->request('GET', '/rota-inexistente');
        $response = $this->router($request)->dispatch($request);

        self::assertSame(404, $response->status());
        self::assertStringContainsString('Página não encontrada', $response->body());
        self::assertStringContainsString('Essa história ainda não está aqui.', $response->body());
        self::assertStringContainsString('/rota-inexistente', $response->body());
    }

    public function testAuthenticationRoutesFailSafelyWithoutConfigurationOrState(): void
    {
        $loginRequest = $this->request('GET', '/login');
        $login = $this->router($loginRequest)->dispatch($loginRequest);
        self::assertSame(200, $login->status());
        self::assertStringContainsString('Google indisponível', $login->body());
        self::assertStringContainsString('Facebook indisponível', $login->body());

        $_SESSION = [];
        $onboardingRequest = $this->request('GET', '/onboarding/username');
        $onboarding = $this->router($onboardingRequest)->dispatch($onboardingRequest);
        self::assertSame(302, $onboarding->status());
        self::assertSame('/login', $onboarding->headers()['Location']);

        $_SESSION = [];
        $logoutRequest = $this->request('POST', '/logout');
        $logout = $this->router($logoutRequest)->dispatch($logoutRequest);
        self::assertSame(403, $logout->status());

        $_SESSION = [];
        $googleRequest = $this->request('POST', '/auth/google');
        $google = $this->router($googleRequest)->dispatch($googleRequest);
        self::assertSame(303, $google->status());
        self::assertSame('/login', $google->headers()['Location']);
    }

    public function testAuthenticatedShellOffersOnlyPostLogoutWithCsrf(): void
    {
        $request = $this->request('GET', '/');
        $response = $this->router($request, true)->dispatch($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('method="post" action="/logout"', $response->body());
        self::assertStringContainsString('name="_token"', $response->body());
        self::assertStringContainsString('href="/perfil"', $response->body());
        self::assertStringNotContainsString('href="/login"', $response->body());

        $logoutRequest = $this->request('GET', '/logout');
        $logoutResponse = $this->router($logoutRequest, true)->dispatch($logoutRequest);
        self::assertSame(404, $logoutResponse->status());
    }

    public function testProfileRouteRedirectsVisitorAndRendersAuthenticatedOwner(): void
    {
        $visitorRequest = $this->request('GET', '/perfil');
        $visitor = $this->router($visitorRequest)->dispatch($visitorRequest);
        self::assertSame(302, $visitor->status());
        self::assertSame('/login', $visitor->headers()['Location']);

        $_SESSION = [];
        $authenticatedRequest = $this->request('GET', '/perfil');
        $authenticated = $this->router($authenticatedRequest, true)->dispatch($authenticatedRequest);
        self::assertSame(200, $authenticated->status());
        self::assertStringContainsString('Route Profile', $authenticated->body());
        self::assertStringContainsString('href="/perfil" aria-current="page"', $authenticated->body());
    }

    public function testSearchAndAboutArePublicRealRoutesWithActiveNavigation(): void
    {
        $searchRequest = $this->request('GET', '/buscar');
        $search = $this->router($searchRequest)->dispatch($searchRequest);
        self::assertSame(200, $search->status());
        self::assertStringContainsString('href="/buscar" aria-current="page"', $search->body());
        self::assertStringContainsString('Seu próximo título começa aqui', $search->body());
        self::assertStringContainsString('bottom-nav__item bottom-nav__item--search is-active', $search->body());

        $aboutRequest = $this->request('GET', '/sobre');
        $about = $this->router($aboutRequest)->dispatch($aboutRequest);
        self::assertSame(200, $about->status());
        self::assertStringContainsString('This product uses the TMDB API but is not endorsed or certified by TMDB.', $about->body());
        self::assertStringContainsString('https://www.themoviedb.org', $about->body());
        self::assertStringContainsString('/assets/images/vendor/tmdb/tmdb-blue-long.svg', $about->body());
        self::assertFileExists(dirname(__DIR__) . '/public/assets/images/vendor/tmdb/tmdb-blue-long.svg');
    }

    public function testMovieAndSeriesDetailRoutesArePublicAndValidateIds(): void
    {
        foreach (['/filmes/603', '/series/1396'] as $path) {
            $request = $this->request('GET', $path);
            $response = $this->router($request)->dispatch($request);
            self::assertSame(503, $response->status());
            self::assertStringContainsString('Detalhes indisponíveis', $response->body());
        }

        foreach (['/filmes/0', '/filmes/abc', '/series/0', '/series/abc'] as $path) {
            $request = $this->request('GET', $path);
            $response = $this->router($request)->dispatch($request);
            self::assertSame(404, $response->status());
            self::assertStringContainsString('Título não encontrado', $response->body());
        }
    }

    private function router(Request $request, bool $authenticated = false): Router
    {
        $session = new Session(false);
        $auth = new Auth($session);
        if ($authenticated) {
            $auth->login(7);
        }
        $csrf = new Csrf($session);
        $pending = new PendingExternalOnboarding($session);
        $identities = new class implements ExternalIdentityFinder, ExternalIdentityLinker, ConnectedProviderReader {
            public function findUserId(string $provider, string $providerUserId): ?int { return null; }
            public function link(int $userId, ExternalIdentity $identity): string { return self::LINKED; }
            public function providersForUser(int $userId): array { return ['google']; }
        };
        $facebook = new class implements FacebookIdentityProvider {
            public function configured(): bool { return false; }
            public function authorizationUrl(string $state): string { return 'https://www.facebook.com/'; }
            public function identityFromCode(string $code): ?ExternalIdentity { return null; }
        };
        $app = [
            'view' => new View(dirname(__DIR__) . '/resources/views'),
            'config' => ['name' => 'Flickary'],
            'router' => new Router(),
            'request' => $request,
            'session' => $session,
            'auth' => $auth,
            'csrf' => $csrf,
            'auth_config' => [
                'google' => [
                    'client_id' => '',
                    'login_uri' => 'http://localhost/auth/google',
                ],
                'facebook' => ['app_id' => '', 'app_secret' => ''],
            ],
            'google_identity_verifier' => new class implements GoogleIdentityVerifier {
                public function verify(string $credential): ?ExternalIdentity
                {
                    return null;
                }
            },
            'external_identities' => $identities,
            'pending_external_onboarding' => $pending,
            'facebook_oauth_state' => new FacebookOAuthState($session),
            'facebook_identity_provider' => $facebook,
            'username_policy' => new UsernamePolicy(),
            'account_creator' => new class implements AccountCreator {
                public function createFromExternalIdentity(ExternalIdentity $identity, string $username): AccountCreationResult
                {
                    return AccountCreationResult::usernameTaken();
                }
            },
            'profiles' => new class implements ProfileStore {
                public function findByUserId(int $userId): ?array
                {
                    return [
                        'username' => 'route.profile',
                        'display_name' => 'Route Profile',
                        'bio' => null,
                        'avatar_url' => null,
                        'cover_url' => null,
                        'is_private' => 0,
                        'created_at' => '2026-09-17 10:00:00',
                    ];
                }

                public function updateProfile(
                    int $userId,
                    string $displayName,
                    ?string $bio,
                    bool $isPrivate,
                ): void {
                }
            },
            'user_media' => new class implements \App\Media\UserMediaStore {
                public function findForUser(int $userId, string $source, string $mediaType, int $sourceId): ?\App\Media\UserMediaItem { return null; }
                public function create(int $userId, \App\Integrations\Tmdb\TmdbMediaDetails $details, string $status): bool { return true; }
                public function updateStatus(int $userId, string $source, string $mediaType, int $sourceId, string $status): bool { return false; }
                public function delete(int $userId, string $source, string $mediaType, int $sourceId): bool { return false; }
                public function paginateForUser(int $userId, ?string $status, ?string $mediaType, int $page, int $perPage): \App\Media\UserMediaPage { return new \App\Media\UserMediaPage([], 0, $page, $perPage); }
                public function countForUser(int $userId): int { return 0; }
            },
            'tmdb' => new class implements TmdbCatalog {
                public function configured(): bool { return false; }
                public function movieDetails(int $id): \App\Integrations\Tmdb\TmdbMediaDetails { throw new \App\Integrations\Tmdb\TmdbException('not_configured'); }
                public function seriesDetails(int $id): \App\Integrations\Tmdb\TmdbMediaDetails { throw new \App\Integrations\Tmdb\TmdbException('not_configured'); }
                public function seasonDetails(int $seriesId,int $seasonNumber): \App\Integrations\Tmdb\TmdbSeasonDetails { throw new \App\Integrations\Tmdb\TmdbException('not_configured'); }
                public function configuration(): TmdbImageConfiguration { throw new \RuntimeException(); }
                public function searchMovies(string $query, int $page = 1): array { return ['page' => 1, 'total_pages' => 0, 'total_results' => 0, 'results' => []]; }
                public function searchSeries(string $query, int $page = 1): array { return ['page' => 1, 'total_pages' => 0, 'total_results' => 0, 'results' => []]; }
            },
            'series_progress' => new class implements \App\Media\UserSeriesProgressStore { public function watchedEpisodeNumbersForSeason(int$u,string$s,int$i,int$n):array{return[];}public function countsBySeason(int$u,string$s,int$i):array{return[];}public function countForSeries(int$u,string$s,int$i):int{return 0;}public function markWatched(int$u,string$s,int$i,int$n,int$e):void{}public function unmarkWatched(int$u,string$s,int$i,int$n,int$e):void{}public function markSeasonWatched(int$u,string$s,int$i,int$n,array$e):void{}public function clearSeason(int$u,string$s,int$i,int$n):void{} },
            'watch_history' => new class implements \App\History\WatchHistoryStore {
                public function createMovie(int $userId, \App\History\WatchHistoryEvent $event): bool { return true; }
                public function createEpisode(int $userId, \App\History\WatchHistoryEvent $event): bool { return true; }
                public function findForUser(int $userId, int $id): ?\App\History\WatchHistoryEntry { return null; }
                public function updateDate(int $userId, int $id, string $watchedOn): bool { return false; }
                public function delete(int $userId, int $id): bool { return false; }
                public function paginateForUser(int $userId, ?string $entryType, int $page, int $perPage): \App\History\WatchHistoryPage { return new \App\History\WatchHistoryPage([], 0, $page, $perPage); }
                public function countForUser(int $userId): int { return 0; }
            },
        ];

        return require dirname(__DIR__) . '/routes/web.php';
    }

    private function request(string $method, string $path): Request
    {
        return new Request(server: [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $path,
        ]);
    }
}
