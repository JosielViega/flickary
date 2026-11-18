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
        self::assertStringContainsString('title="Entre para acessar o Social"', $response->body());
        self::assertStringNotContainsString('href="/social"', $response->body());
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

    public function testSuspendedAccountPageIsPublicAndNeutralWithoutServerContext():void
    {
        $request=$this->request('GET','/conta/suspensa');$response=$this->router($request)->dispatch($request);self::assertSame(200,$response->status());self::assertStringContainsString('Não há uma suspensão ativa',$response->body());self::assertStringNotContainsString('moderator',$response->body());
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

    public function testSocialRouteIsPrivateAndActivatesSocialNavigation(): void
    {
        $visitorRequest = $this->request('GET', '/social');
        $visitor = $this->router($visitorRequest)->dispatch($visitorRequest);
        self::assertSame(303, $visitor->status());
        self::assertSame('/login', $visitor->headers()['Location']);

        $_SESSION = [];
        $authenticatedRequest = $this->request('GET', '/social');
        $authenticated = $this->router($authenticatedRequest, true)->dispatch($authenticatedRequest);
        self::assertSame(200, $authenticated->status());
        self::assertStringContainsString('Seu Social ainda está vazio', $authenticated->body());
        self::assertStringContainsString('href="/social" aria-current="page"', $authenticated->body());
        self::assertStringContainsString('bottom-nav__item is-active', $authenticated->body());

        foreach (['/social/atividades/31/curtir', '/social/atividades/31/descurtir', '/social/atividades/31/comentarios', '/social/comentarios/9/remover'] as $path) {
            $_SESSION = [];
            $mutation = $this->request('POST', $path);
            $visitorMutation = $this->router($mutation)->dispatch($mutation);
            self::assertSame(303, $visitorMutation->status());
            self::assertSame('/login', $visitorMutation->headers()['Location']);
        }

        foreach (['/social/atividades/31', '/perfil/comentarios'] as $path) {
            $_SESSION = [];
            $privateRequest = $this->request('GET', $path);
            $private = $this->router($privateRequest)->dispatch($privateRequest);
            self::assertSame(303, $private->status());
            self::assertSame('/login', $private->headers()['Location']);
        }
    }

    public function testNotificationRoutesArePrivateAndRenderForAuthenticatedUser(): void
    {
        $visitorRequest = $this->request('GET', '/notificacoes');
        $visitor = $this->router($visitorRequest)->dispatch($visitorRequest);
        self::assertSame(303, $visitor->status());
        self::assertSame('/login', $visitor->headers()['Location']);

        foreach (['/notificacoes/10/abrir', '/notificacoes/ler-todas'] as $path) {
            $_SESSION = [];
            $mutation = $this->request('POST', $path);
            $visitorMutation = $this->router($mutation)->dispatch($mutation);
            self::assertSame(303, $visitorMutation->status());
            self::assertSame('/login', $visitorMutation->headers()['Location']);
        }

        $_SESSION = [];
        $authenticatedRequest = $this->request('GET', '/notificacoes');
        $authenticated = $this->router($authenticatedRequest, true)->dispatch($authenticatedRequest);
        self::assertSame(200, $authenticated->status());
        self::assertStringContainsString('Você ainda não tem notificações.', $authenticated->body());
        self::assertStringContainsString('href="/social" aria-current="page"', $authenticated->body());
        self::assertStringContainsString('bottom-nav__item is-active', $authenticated->body());
    }

    public function testMessageRoutesArePrivateAndRenderInboxForAuthenticatedUser(): void
    {
        foreach (['/mensagens', '/mensagens/com/other.user'] as $path) {
            $_SESSION = [];
            $request = $this->request('GET', $path);
            $response = $this->router($request)->dispatch($request);
            self::assertSame(303, $response->status()); self::assertSame('/login', $response->headers()['Location']);
        }
        foreach (['/mensagens/com/other.user', '/mensagens/com/other.user/ler', '/mensagens/com/other.user/abrir'] as $path) {
            $_SESSION = [];
            $request = $this->request('POST', $path);
            $response = $this->router($request)->dispatch($request);
            self::assertSame(303, $response->status()); self::assertSame('/login', $response->headers()['Location']);
        }
        $_SESSION = [];
        $request = $this->request('GET', '/mensagens');
        $response = $this->router($request, true)->dispatch($request);
        self::assertSame(200, $response->status());
        self::assertStringContainsString('Você ainda não tem conversas.', $response->body());
        self::assertStringContainsString('href="/social" aria-current="page"', $response->body());
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

    public function testPreferenceRoutesArePrivateAndUseLibraryNavigation(): void
    {
        foreach (['/favoritos', '/avaliacoes'] as $path) {
            $_SESSION = [];
            $visitorRequest = $this->request('GET', $path);
            $visitor = $this->router($visitorRequest)->dispatch($visitorRequest);
            self::assertSame(303, $visitor->status());
            self::assertSame('/login', $visitor->headers()['Location']);

            $_SESSION = [];
            $ownerRequest = $this->request('GET', $path);
            $owner = $this->router($ownerRequest, true)->dispatch($ownerRequest);
            self::assertSame(200, $owner->status());
            self::assertStringContainsString('href="/minha-lista" aria-current="page"', $owner->body());
        }

        foreach (['/filmes/603/preferencias', '/series/1396/preferencias'] as $path) {
            $_SESSION = [];
            $request = $this->request('POST', $path);
            $response = $this->router($request)->dispatch($request);
            self::assertSame(303, $response->status());
            self::assertSame('/login', $response->headers()['Location']);
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
        $socialComments = new class implements \App\Social\SocialActivityCommentReader, \App\Social\SocialActivityCommentStore {
            public function detailForViewer(int$userId,int$historyId):?\App\Social\SocialActivityDetail{return null;}
            public function paginateForActivity(int$historyId,int$page,int$perPage):\App\Social\SocialActivityCommentPage{return new \App\Social\SocialActivityCommentPage([],0,$page,$perPage);}
            public function paginateForAuthor(int$userId,int$page,int$perPage):\App\Social\SocialOwnCommentPage{return new \App\Social\SocialOwnCommentPage([],0,$page,$perPage);}
            public function createAuthorized(int$userId,int$historyId,\App\Social\SocialCommentBody$body):\App\Social\SocialActivityCommentCreateResult{return \App\Social\SocialActivityCommentCreateResult::Hidden;}
            public function deleteAuthorized(int$userId,int$commentId):?int{return null;}
        };
        $socialNotifications = new class implements \App\Notifications\SocialNotificationReader, \App\Notifications\SocialNotificationStore {
            public function paginateForUser(int$userId,int$page,int$perPage):\App\Notifications\SocialNotificationPage{return new \App\Notifications\SocialNotificationPage([],0,$page,$perPage);}
            public function countUnread(int$userId):int{return 0;}
            public function findForRecipient(int$recipientUserId,int$notificationId):?\App\Notifications\SocialNotification{return null;}
            public function markRead(int$recipientUserId,int$notificationId):bool{return false;}
            public function markAllRead(int$recipientUserId):void{}
            public function followRequest(int$recipientUserId,int$actorUserId):void{}public function removeFollowRequest(int$recipientUserId,int$actorUserId):void{}public function followAccepted(int$recipientUserId,int$actorUserId):void{}public function followAcceptedForPendingTarget(int$targetUserId):void{}public function removeFollowRequestsForTarget(int$targetUserId):void{}public function newFollower(int$recipientUserId,int$actorUserId):void{}public function activityLike(int$historyId,int$actorUserId):void{}public function removeActivityLike(int$historyId,int$actorUserId):void{}public function activityComment(int$historyId,int$commentId,int$actorUserId):void{}
        };
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
        $accountAccess = new class implements \App\Moderation\AccountAccessReader { public function activeSuspensionUntil(int$userId):?string{return null;} };
        $moderationActions = new class implements \App\Moderation\ModerationActionReader {
            public function paginateAll(int$page,int$perPage):\App\Moderation\ModerationActionPage{return new \App\Moderation\ModerationActionPage([],0,$page,$perPage);}
            public function paginateForUser(int$userId,int$page,int$perPage):\App\Moderation\ModerationActionPage{return new \App\Moderation\ModerationActionPage([],0,$page,$perPage);}
            public function findUserByUsername(string$username):?\App\Moderation\ModeratedUser{return null;}
            public function findUserById(int$userId):?\App\Moderation\ModeratedUser{return null;}
            public function liveCommentMatchesReport(\App\Moderation\SocialReport$report):bool{return false;}
        };
        $moderationService = new class implements \App\Moderation\ModerationActionStore {
            public function suspendFromReport(int$reportId,int$adminUserId,\App\Moderation\SuspensionDuration$duration,\App\Moderation\ModerationNote$note):\App\Moderation\ModerationActionResult{return \App\Moderation\ModerationActionResult::NotFound;}
            public function unsuspend(string$username,int$adminUserId,\App\Moderation\ModerationNote$note):\App\Moderation\ModerationActionResult{return \App\Moderation\ModerationActionResult::NotFound;}
            public function removeReportedComment(int$reportId,int$adminUserId,\App\Moderation\ModerationNote$note):\App\Moderation\ModerationActionResult{return \App\Moderation\ModerationActionResult::NotFound;}
        };
        $app = [
            'view' => new View(dirname(__DIR__) . '/resources/views'),
            'config' => ['name' => 'Flickary'],
            'public_url' => new \App\Core\PublicUrl('https://app.example.test/flickary'),
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
            'account_suspension_guard' => new \App\Moderation\AccountSuspensionGuard($auth,$session,$accountAccess),
            'moderation_actions' => $moderationActions,
            'moderation_service' => $moderationService,
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
                        'share_watch_activity' => 0,
                        'share_watch_activity_since' => null,
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
            'public_profiles' => new class implements \App\Social\PublicProfileReader {
                public function findByUsername(string $username): ?\App\Social\PublicProfile { return null; }
                public function statisticsForUser(int $userId): \App\Social\PublicProfileStatistics { return new \App\Social\PublicProfileStatistics(0,0,0,null,0,0,0); }
                public function recentFavorites(int $userId, int $limit=6): array { return []; }
                public function search(string $query,int $page,int $perPage): \App\Social\PublicProfilePage { return new \App\Social\PublicProfilePage([],0,$page,$perPage); }
            },
            'user_follows' => new class implements \App\Social\FollowStore {
                public function follow(int $followerUserId,int $followedUserId):void{}
                public function unfollow(int $followerUserId,int $followedUserId):void{}
                public function isFollowing(int $followerUserId,int $followedUserId):bool{return false;}
                public function areMutualFollowers(int $userA,int $userB):bool{return false;}
                public function countFollowers(int $userId):int{return 0;}
                public function countFollowing(int $userId):int{return 0;}
                public function paginateFollowers(int $userId,int $page,int $perPage):\App\Social\PublicProfilePage{return new \App\Social\PublicProfilePage([],0,$page,$perPage);}
                public function paginateFollowing(int $userId,int $page,int $perPage):\App\Social\PublicProfilePage{return new \App\Social\PublicProfilePage([],0,$page,$perPage);}
            },
            'user_media' => new class implements \App\Media\UserMediaStore {
                public function findForUser(int $userId, string $source, string $mediaType, int $sourceId): ?\App\Media\UserMediaItem { return null; }
                public function create(int $userId, \App\Integrations\Tmdb\TmdbMediaDetails $details, string $status, ?bool $isAnime = null): bool { return true; }
                public function updateStatus(int $userId, string $source, string $mediaType, int $sourceId, string $status): bool { return false; }
                public function delete(int $userId, string $source, string $mediaType, int $sourceId): bool { return false; }
                public function paginateForUser(int $userId, ?string $status, ?string $mediaType, int $page, int $perPage, bool $animeOnly = false): \App\Media\UserMediaPage { return new \App\Media\UserMediaPage([], 0, $page, $perPage); }
                public function countForUser(int $userId): int { return 0; }
            },
            'media_preferences' => new class implements \App\Preferences\UserMediaPreferenceStore {
                public function findForUser(int$userId,string$source,string$mediaType,int$sourceId):?\App\Preferences\UserMediaPreference{return null;}
                public function create(int$userId,\App\Preferences\UserMediaPreferenceDraft$draft):bool{return true;}
                public function update(int$userId,string$source,string$mediaType,int$sourceId,bool$favorite,?int$rating):bool{return false;}
                public function delete(int$userId,string$source,string$mediaType,int$sourceId):bool{return false;}
                public function paginateFavorites(int$userId,?string$mediaType,bool$animeOnly,int$page,int$perPage):\App\Preferences\UserMediaPreferencePage{return new \App\Preferences\UserMediaPreferencePage([],0,$page,$perPage);}
                public function paginateRated(int$userId,?string$mediaType,bool$animeOnly,int$page,int$perPage):\App\Preferences\UserMediaPreferencePage{return new \App\Preferences\UserMediaPreferencePage([],0,$page,$perPage);}
                public function countFavorites(int$userId):int{return 0;}public function countRated(int$userId):int{return 0;}
            },
            'tmdb' => new class implements TmdbCatalog {
                public function configured(): bool { return false; }
                public function movieDetails(int $id): \App\Integrations\Tmdb\TmdbMediaDetails { throw new \App\Integrations\Tmdb\TmdbException('not_configured'); }
                public function seriesDetails(int $id): \App\Integrations\Tmdb\TmdbMediaDetails { throw new \App\Integrations\Tmdb\TmdbException('not_configured'); }
                public function seasonDetails(int $seriesId,int $seasonNumber): \App\Integrations\Tmdb\TmdbSeasonDetails { throw new \App\Integrations\Tmdb\TmdbException('not_configured'); }
                public function configuration(): TmdbImageConfiguration { throw new \RuntimeException(); }
                public function searchMovies(string $query, int $page = 1): array { return ['page' => 1, 'total_pages' => 0, 'total_results' => 0, 'results' => []]; }
                public function searchSeries(string $query, int $page = 1): array { return ['page' => 1, 'total_pages' => 0, 'total_results' => 0, 'results' => []]; }
                public function discoverAnimeMovies(int $page = 1): array { return ['page'=>$page,'total_pages'=>0,'total_results'=>0,'results'=>[]]; }
                public function discoverAnimeSeries(int $page = 1): array { return ['page'=>$page,'total_pages'=>0,'total_results'=>0,'results'=>[]]; }
            },
            'series_progress' => new class implements \App\Media\UserSeriesProgressStore { public function watchedEpisodeNumbersForSeason(int$u,string$s,int$i,int$n):array{return[];}public function countsBySeason(int$u,string$s,int$i):array{return[];}public function countForSeries(int$u,string$s,int$i):int{return 0;}public function markWatched(int$u,string$s,int$i,int$n,int$e):void{}public function unmarkWatched(int$u,string$s,int$i,int$n,int$e):void{}public function markSeasonWatched(int$u,string$s,int$i,int$n,array$e):void{}public function clearSeason(int$u,string$s,int$i,int$n):void{} },
            'watch_history' => new class implements \App\History\WatchHistoryStore {
                public function createMovie(int $userId, \App\History\WatchHistoryEvent $event): bool { return true; }
                public function createEpisode(int $userId, \App\History\WatchHistoryEvent $event): bool { return true; }
                public function findForUser(int $userId, int $id): ?\App\History\WatchHistoryEntry { return null; }
                public function updateDate(int $userId, int $id, string $watchedOn): bool { return false; }
                public function delete(int $userId, int $id): bool { return false; }
                public function paginateForUser(int $userId, ?string $entryType, int $page, int $perPage, bool $animeOnly = false): \App\History\WatchHistoryPage { return new \App\History\WatchHistoryPage([], 0, $page, $perPage); }
                public function countForUser(int $userId): int { return 0; }
            },
            'user_schedule' => new class implements \App\Schedule\ScheduleStore {
                public function findForUserIdentity(int $userId,string $source,string $entryType,int $sourceId,int $seasonNumber=0,int $episodeNumber=0):?\App\Schedule\ScheduleEntry{return null;}
                public function findForUser(int $userId,int $id):?\App\Schedule\ScheduleEntry{return null;}
                public function create(int $userId,\App\Schedule\ScheduleItem $item):bool{return true;}
                public function updateDate(int $userId,int $id,string $scheduledOn):bool{return false;}
                public function delete(int $userId,int $id):bool{return false;}
                public function paginateForUser(int $userId,?string $entryType,string $today,int$page,int$perPage,bool$animeOnly=false):\App\Schedule\SchedulePage{return new \App\Schedule\SchedulePage([],0,$page,$perPage);}
                public function countForUser(int $userId):int{return 0;}
                public function episodeSchedulesForSeason(int $userId,string $source,int $seriesId,int $seasonNumber):array{return[];}
            },
            'personal_statistics' => new class implements \App\Statistics\StatisticsReader {
                public function readForUser(int $userId,string $today):\App\Statistics\PersonalStatistics{return new \App\Statistics\PersonalStatistics(0,0,0,0,0,0,0,0,0,0,0,[],array_fill_keys(array_keys(\App\Media\UserMediaStatus::options()),0),0,0,0,0,0,0);}
            },
            'user_follow_requests' => new \App\Social\NullFollowRequestStore(),
            'social_feed' => new class implements \App\Social\SocialFeedReader {
                public function paginateForViewer(int$userId,int$page,int$perPage):\App\Social\SocialFeedPage{return new \App\Social\SocialFeedPage([],0,$page,$perPage);}
            },
            'social_activity_likes' => new class implements \App\Social\SocialActivityLikeStore {
                public function likeVisibleActivity(int$viewerUserId,int$historyId):\App\Social\SocialActivityLikeResult{return \App\Social\SocialActivityLikeResult::Hidden;}
                public function unlike(int$viewerUserId,int$historyId):void{}
            },
            'social_activity_comments' => $socialComments,
            'social_notifications' => $socialNotifications,
            'direct_messages' => new class implements \App\Messaging\DirectMessageReader, \App\Messaging\DirectMessageStore {
                public function conversationExists(int$userA,int$userB):bool{return false;}
                public function conversationForUsers(int$currentUserId,int$otherUserId):?\App\Messaging\DirectConversation{return null;}
                public function paginateConversations(int$userId,int$page,int$perPage):\App\Messaging\DirectConversationPage{return new \App\Messaging\DirectConversationPage([],0,$page,$perPage);}
                public function paginateMessages(int$currentUserId,int$otherUserId,int$page,int$perPage):\App\Messaging\DirectMessagePage{return new \App\Messaging\DirectMessagePage([],0,$page,$perPage);}
                public function countUnreadForUser(int$userId):int{return 0;}
                public function countUnreadForConversation(int$currentUserId,int$otherUserId):int{return 0;}
                public function send(int$senderUserId,int$otherUserId,\App\Messaging\DirectMessageBody$body):bool{return false;}
                public function markRead(int$currentUserId,int$otherUserId):bool{return false;}
            },
            'admins' => new class implements \App\Administration\AdminAccess {
                public function isAdmin(int $userId): bool { return false; }
            },
            'social_reports' => new class implements \App\Moderation\ReportStore, \App\Moderation\ReportReader, \App\Moderation\AdminReportReader {
                public function reportableComment(int $viewerUserId,int $commentId):?\App\Moderation\ReportTarget{return null;}
                public function reportableMessage(int $viewerUserId,int $messageId):?\App\Moderation\ReportTarget{return null;}
                public function create(\App\Moderation\ReportSubmission $submission):\App\Moderation\ReportCreateResult{return \App\Moderation\ReportCreateResult::Created;}
                public function paginateOwn(int $reporterUserId,int $page,int $perPage):\App\Moderation\SocialReportPage{return new \App\Moderation\SocialReportPage([],0,$page,$perPage);}
                public function paginateAdmin(?\App\Moderation\ReportStatus $status,int $page,int $perPage):\App\Moderation\SocialReportPage{return new \App\Moderation\SocialReportPage([],0,$page,$perPage);}
                public function findAdmin(int $reportId):?\App\Moderation\SocialReport{return null;}
                public function review(int $reportId,int $reviewerUserId,\App\Moderation\ReportStatus $status,?string $moderatorNote):bool{return false;}
            },
            'private_profile_access_policy' => new \App\Social\PrivateProfileAccessPolicy(),
            'profile_updater' => new class implements \App\Profiles\ProfileUpdater {
                public function update(int$userId,string$displayName,?string$bio,bool$wasPrivate,bool$isPrivate,bool$shareWatchActivity):void{}
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
