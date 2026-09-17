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
use App\Controllers\GoogleAuthController;
use App\Controllers\LoginController;
use App\Controllers\LogoutController;
use App\Controllers\OnboardingController;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use PHPUnit\Framework\TestCase;

final class AuthenticationControllersTest extends TestCase
{
    private bool $sessionExisted;
    private array $previousSession;
    private Session $session;
    private Auth $auth;
    private Csrf $csrf;

    protected function setUp(): void
    {
        $this->sessionExisted = isset($_SESSION);
        $this->previousSession = $_SESSION ?? [];
        $_SESSION = [];
        $this->session = new Session(false);
        $this->auth = new Auth($this->session);
        $this->csrf = new Csrf($this->session);
    }

    protected function tearDown(): void
    {
        if ($this->sessionExisted) {
            $_SESSION = $this->previousSession;
        } else {
            unset($_SESSION);
        }
    }

    public function testLoginShowsFriendlyUnavailableStateWithoutClientId(): void
    {
        $response = (new LoginController(
            $this->view(),
            $this->auth,
            $this->session,
            ['client_id' => '', 'login_uri' => 'http://localhost/auth/google'],
        ))->show();

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Acesso indisponível neste ambiente', $response->body());
        self::assertStringNotContainsString('accounts.google.com/gsi/client', $response->body());
        self::assertStringNotContainsString('type="password"', $response->body());
    }

    public function testLoginRendersExplicitGoogleButtonWhenConfigured(): void
    {
        $response = (new LoginController(
            $this->view(),
            $this->auth,
            $this->session,
            ['client_id' => 'client-id', 'login_uri' => 'https://flickary.test/auth/google'],
        ))->show();

        self::assertStringContainsString('accounts.google.com/gsi/client', $response->body());
        self::assertStringContainsString('data-client_id="client-id"', $response->body());
        self::assertStringContainsString('data-ux_mode="popup"', $response->body());
        self::assertStringContainsString('data-auto_prompt="false"', $response->body());
    }

    public function testLoginRedirectsAuthenticatedUserHome(): void
    {
        $this->auth->login(7);
        $response = (new LoginController($this->view(), $this->auth, $this->session, [
            'client_id' => 'client-id',
        ]))->show();

        self::assertSame(302, $response->status());
        self::assertSame('/', $response->headers()['Location']);
    }

    public function testGoogleEndpointRejectsMismatchedDoubleSubmitCsrfBeforeVerification(): void
    {
        $called = false;
        $controller = $this->googleController(
            $this->verifier(new GoogleIdentity('subject', null, false, null, null), $called),
            $this->finder(null),
        );
        $request = new Request(
            parsedBody: ['credential' => 'jwt', 'g_csrf_token' => 'body-token'],
            cookies: ['g_csrf_token' => 'cookie-token'],
        );

        $response = $controller->handle($request);

        self::assertSame(303, $response->status());
        self::assertSame('/login', $response->headers()['Location']);
        self::assertFalse($called);
        self::assertFalse($this->auth->check());
    }

    public function testGoogleEndpointRejectsInvalidCredential(): void
    {
        $called = false;
        $response = $this->googleController($this->verifier(null, $called), $this->finder(null))->handle(
            $this->googleRequest(),
        );

        self::assertTrue($called);
        self::assertSame('/login', $response->headers()['Location']);
        self::assertFalse($this->auth->check());
    }

    public function testExistingGoogleIdentityLogsInAndClearsOldPendingState(): void
    {
        $identity = new GoogleIdentity('subject', 'person@example.com', true, 'Person', null);
        $pending = new PendingGoogleOnboarding($this->session, static fn (): int => 1000);
        $pending->store(new GoogleIdentity('old-subject', null, false, null, null));
        $called = false;
        $controller = $this->googleController($this->verifier($identity, $called), $this->finder(17), $pending);

        $response = $controller->handle($this->googleRequest());

        self::assertSame(303, $response->status());
        self::assertSame('/', $response->headers()['Location']);
        self::assertSame(17, $this->auth->id());
        self::assertNull($pending->current());
    }

    public function testFirstGoogleAccessCreatesPendingOnboardingWithoutLoggingIn(): void
    {
        $identity = new GoogleIdentity('new-subject', 'person@example.com', true, 'Person', null);
        $pending = new PendingGoogleOnboarding($this->session, static fn (): int => 1000);
        $called = false;
        $controller = $this->googleController($this->verifier($identity, $called), $this->finder(null), $pending);

        $response = $controller->handle($this->googleRequest());

        self::assertSame('/onboarding/username', $response->headers()['Location']);
        self::assertFalse($this->auth->check());
        self::assertEquals($identity, $pending->current());
    }

    public function testOnboardingWithoutPendingRedirectsToLogin(): void
    {
        $response = $this->onboardingController($this->creator(AccountCreationResult::usernameTaken()))->show();

        self::assertSame(302, $response->status());
        self::assertSame('/login', $response->headers()['Location']);
    }

    public function testOnboardingRejectsInvalidFlickaryCsrf(): void
    {
        $this->storePendingIdentity();
        $response = $this->onboardingController($this->creator(AccountCreationResult::usernameTaken()))->store(
            new Request(parsedBody: ['_token' => 'invalid', 'username' => 'valid_user']),
        );

        self::assertSame(403, $response->status());
        self::assertFalse($this->auth->check());
    }

    public function testOnboardingRejectsInvalidUsernameBeforeAccountCreation(): void
    {
        $this->storePendingIdentity();
        $creator = $this->creator(AccountCreationResult::created(21));
        $response = $this->onboardingController($creator)->store(new Request(parsedBody: [
            '_token' => $this->csrf->token(),
            'username' => '.invalid',
        ]));

        self::assertSame(422, $response->status());
        self::assertNull($creator->receivedUsername);
        self::assertStringContainsString('Use de 3 a 30 caracteres', $response->body());
    }

    public function testOnboardingReportsOccupiedUsername(): void
    {
        $this->storePendingIdentity();
        $creator = $this->creator(AccountCreationResult::usernameTaken());
        $response = $this->onboardingController($creator)->store(new Request(parsedBody: [
            '_token' => $this->csrf->token(),
            'username' => 'Cinema.User',
        ]));

        self::assertSame(422, $response->status());
        self::assertSame('cinema.user', $creator->receivedUsername);
        self::assertStringContainsString('Esse username já está em uso', $response->body());
    }

    public function testOnboardingStopsOnVerifiedEmailConflict(): void
    {
        $this->storePendingIdentity();
        $response = $this->onboardingController($this->creator(AccountCreationResult::emailConflict()))->store(
            new Request(parsedBody: [
                '_token' => $this->csrf->token(),
                'username' => 'cinema_user',
            ]),
        );

        self::assertSame(409, $response->status());
        self::assertStringContainsString('vinculação segura entre contas', $response->body());
        self::assertFalse($this->auth->check());
    }

    public function testSuccessfulOnboardingClearsPendingAndLogsInInternalUserId(): void
    {
        $pending = $this->storePendingIdentity();
        $response = $this->onboardingController($this->creator(AccountCreationResult::created(29)), $pending)->store(
            new Request(parsedBody: [
                '_token' => $this->csrf->token(),
                'username' => 'Cinema_User',
            ]),
        );

        self::assertSame(303, $response->status());
        self::assertSame('/', $response->headers()['Location']);
        self::assertSame(29, $this->auth->id());
        self::assertNull($pending->current());
    }

    public function testConcurrentIdentityCreationLogsIntoResolvedAccount(): void
    {
        $pending = $this->storePendingIdentity();
        $response = $this->onboardingController(
            $this->creator(AccountCreationResult::identityExists(31)),
            $pending,
        )->store(new Request(parsedBody: [
            '_token' => $this->csrf->token(),
            'username' => 'cinema_user',
        ]));

        self::assertSame(31, $this->auth->id());
        self::assertNull($pending->current());
    }

    public function testLogoutRequiresFlickaryCsrfAndUsesPostPrimitive(): void
    {
        $this->auth->login(42);
        $controller = new LogoutController($this->auth, $this->csrf);

        $invalid = $controller->handle(new Request(parsedBody: ['_token' => 'invalid']));
        self::assertSame(403, $invalid->status());
        self::assertTrue($this->auth->check());

        $valid = $controller->handle(new Request(parsedBody: ['_token' => $this->csrf->token()]));
        self::assertSame(303, $valid->status());
        self::assertSame('/', $valid->headers()['Location']);
        self::assertFalse($this->auth->check());
    }

    private function googleController(
        GoogleIdentityVerifier $verifier,
        ExternalIdentityFinder $finder,
        ?PendingGoogleOnboarding $pending = null,
    ): GoogleAuthController {
        return new GoogleAuthController(
            $this->auth,
            $this->session,
            $verifier,
            $finder,
            $pending ?? new PendingGoogleOnboarding($this->session),
            'client-id',
        );
    }

    private function verifier(?GoogleIdentity $identity, bool &$called): GoogleIdentityVerifier
    {
        return new class($identity, $called) implements GoogleIdentityVerifier {
            public function __construct(
                private readonly ?GoogleIdentity $identity,
                private bool &$called,
            ) {
            }

            public function verify(string $credential): ?GoogleIdentity
            {
                $this->called = true;
                return $this->identity;
            }
        };
    }

    private function finder(?int $userId): ExternalIdentityFinder
    {
        return new class($userId) implements ExternalIdentityFinder {
            public function __construct(private readonly ?int $userId)
            {
            }

            public function findUserId(string $provider, string $providerUserId): ?int
            {
                return $this->userId;
            }
        };
    }

    private function creator(AccountCreationResult $result): AccountCreator
    {
        return new class($result) implements AccountCreator {
            public ?string $receivedUsername = null;

            public function __construct(private readonly AccountCreationResult $result)
            {
            }

            public function createFromGoogle(GoogleIdentity $identity, string $username): AccountCreationResult
            {
                $this->receivedUsername = $username;
                return $this->result;
            }
        };
    }

    private function onboardingController(
        AccountCreator $creator,
        ?PendingGoogleOnboarding $pending = null,
    ): OnboardingController {
        return new OnboardingController(
            $this->view(),
            $this->auth,
            $this->csrf,
            $this->session,
            $pending ?? new PendingGoogleOnboarding($this->session),
            new UsernamePolicy(),
            $creator,
        );
    }

    private function storePendingIdentity(): PendingGoogleOnboarding
    {
        $pending = new PendingGoogleOnboarding($this->session);
        $pending->store(new GoogleIdentity(
            'subject',
            'person@example.com',
            true,
            'Person',
            'https://example.test/avatar.jpg',
        ));

        return $pending;
    }

    private function googleRequest(): Request
    {
        return new Request(
            parsedBody: ['credential' => 'jwt', 'g_csrf_token' => 'matching-token'],
            cookies: ['g_csrf_token' => 'matching-token'],
        );
    }

    private function view(): \App\Core\View
    {
        return new \App\Core\View(dirname(__DIR__) . '/resources/views');
    }
}
