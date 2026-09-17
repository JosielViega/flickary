<?php

declare(strict_types=1);

namespace Tests;

use App\Authentication\AccountCreationResult;
use App\Authentication\AccountCreator;
use App\Authentication\ExternalIdentityFinder;
use App\Authentication\GoogleIdentity;
use App\Authentication\GoogleIdentityVerifier;
use App\Authentication\PendingGoogleOnboarding;
use App\Authentication\UsernamePolicy;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Profiles\ProfileStore;
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
        self::assertStringNotContainsString('<form', $response->body());
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
        self::assertStringContainsString('Acesso indisponível neste ambiente', $login->body());

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

    private function router(Request $request, bool $authenticated = false): Router
    {
        $session = new Session(false);
        $auth = new Auth($session);
        if ($authenticated) {
            $auth->login(7);
        }
        $csrf = new Csrf($session);
        $pending = new PendingGoogleOnboarding($session);
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
            ],
            'google_identity_verifier' => new class implements GoogleIdentityVerifier {
                public function verify(string $credential): ?GoogleIdentity
                {
                    return null;
                }
            },
            'external_identities' => new class implements ExternalIdentityFinder {
                public function findUserId(string $provider, string $providerUserId): ?int
                {
                    return null;
                }
            },
            'pending_google_onboarding' => $pending,
            'username_policy' => new UsernamePolicy(),
            'account_creator' => new class implements AccountCreator {
                public function createFromGoogle(GoogleIdentity $identity, string $username): AccountCreationResult
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
