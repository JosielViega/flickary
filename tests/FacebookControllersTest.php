<?php

declare(strict_types=1);

namespace Tests;

use App\Authentication\ConnectedProviderReader;
use App\Authentication\ExternalIdentity;
use App\Authentication\ExternalIdentityFinder;
use App\Authentication\ExternalIdentityLinker;
use App\Authentication\FacebookIdentityProvider;
use App\Authentication\FacebookOAuthState;
use App\Authentication\PendingExternalOnboarding;
use App\Controllers\FacebookAuthController;
use App\Controllers\FacebookConnectionController;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use PHPUnit\Framework\TestCase;

final class FacebookControllersTest extends TestCase
{
    private Session $session;
    private Auth $auth;
    private Csrf $csrf;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->session = new Session(false);
        $this->auth = new Auth($this->session);
        $this->csrf = new Csrf($this->session);
    }

    protected function tearDown(): void { unset($_SESSION); }

    public function testExistingFacebookIdentityLogsInAndNewIdentityStartsGenericOnboarding(): void
    {
        $state = new FacebookOAuthState($this->session, static fn (): int => 1000);
        $pending = new PendingExternalOnboarding($this->session, static fn (): int => 1000);
        $identities = $this->identities(71);
        $controller = $this->authController($this->facebook(), $state, $pending, $identities);
        $token = $state->issue('login');

        $response = $controller->callback(new Request(queryParams: ['state' => $token, 'code' => 'code']));
        self::assertSame('/', $response->headers()['Location']);
        self::assertSame(71, $this->auth->id());
        self::assertNull($pending->current());

        $this->auth->logout();
        $state = new FacebookOAuthState($this->session, static fn (): int => 1000);
        $controller = $this->authController($this->facebook(), $state, $pending, $this->identities(null));
        $token = $state->issue('login');
        $response = $controller->callback(new Request(queryParams: ['state' => $token, 'code' => 'code']));
        self::assertSame('/onboarding/username', $response->headers()['Location']);
        self::assertSame('facebook', $pending->current()?->provider);
        self::assertNull($pending->current()?->email);
    }

    public function testCallbackRejectsTamperingCancellationAndChangedLinkSession(): void
    {
        $state = new FacebookOAuthState($this->session, static fn (): int => 1000);
        $controller = $this->authController($this->facebook(), $state, new PendingExternalOnboarding($this->session), $this->identities(null));
        self::assertSame('/login', $controller->callback(new Request(queryParams: ['state' => str_repeat('x', 64), 'code' => 'code']))->headers()['Location']);

        $token = $state->issue('login');
        self::assertSame('/login', $controller->callback(new Request(queryParams: ['state' => $token, 'error' => 'access_denied']))->headers()['Location']);

        $this->auth->login(10);
        $token = $state->issue('link', 10);
        $this->auth->logout();
        $this->auth->login(11);
        self::assertSame('/perfil', $controller->callback(new Request(queryParams: ['state' => $token, 'code' => 'code']))->headers()['Location']);
    }

    public function testLinkStartRequiresAuthCsrfConfigurationAndUsesBoundState(): void
    {
        $state = new FacebookOAuthState($this->session, static fn (): int => 1000);
        $providers = $this->identities(null);
        $controller = new FacebookConnectionController($this->auth, $this->csrf, $this->session, $this->facebook(), $state, $providers);
        self::assertSame('/login', $controller->store(new Request())->headers()['Location']);

        $this->auth->login(33);
        self::assertSame('/perfil', $controller->store(new Request(parsedBody: ['_token' => 'bad']))->headers()['Location']);

        $response = $controller->store(new Request(parsedBody: ['_token' => $this->csrf->token()]));
        self::assertStringStartsWith('https://www.facebook.com/', $response->headers()['Location']);
        parse_str((string) parse_url($response->headers()['Location'], PHP_URL_QUERY), $query);
        self::assertSame(['intent' => 'link', 'user_id' => 33], $state->consume($query['state']));
    }

    private function authController(FacebookIdentityProvider $facebook, FacebookOAuthState $state, PendingExternalOnboarding $pending, object $identities): FacebookAuthController
    {
        return new FacebookAuthController($this->auth, $this->session, $facebook, $state, $identities, $identities, $pending);
    }

    private function facebook(): FacebookIdentityProvider
    {
        return new class implements FacebookIdentityProvider {
            public function configured(): bool { return true; }
            public function authorizationUrl(string $state): string { return 'https://www.facebook.com/v26.0/dialog/oauth?state=' . $state; }
            public function identityFromCode(string $code): ?ExternalIdentity { return new ExternalIdentity('facebook', 'fb-123', null, false, 'Film Person', null); }
        };
    }

    private function identities(?int $foundUserId): object
    {
        return new class($foundUserId) implements ExternalIdentityFinder, ExternalIdentityLinker, ConnectedProviderReader {
            public function __construct(private readonly ?int $foundUserId) {}
            public function findUserId(string $provider, string $providerUserId): ?int { return $this->foundUserId; }
            public function link(int $userId, ExternalIdentity $identity): string { return self::LINKED; }
            public function providersForUser(int $userId): array { return []; }
        };
    }
}
