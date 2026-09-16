<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class AuthTest extends TestCase
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
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        if ($this->sessionExisted) {
            $_SESSION = $this->previousSession;
        } else {
            unset($_SESSION);
        }
    }

    public function testStartsUnauthenticated(): void
    {
        $auth = new Auth(new Session(false));

        self::assertFalse($auth->check());
        self::assertNull($auth->id());
    }

    public function testLoginStoresOnlyTheInternalUserId(): void
    {
        $auth = new Auth(new Session(false));

        $auth->login(42);

        self::assertTrue($auth->check());
        self::assertSame(42, $auth->id());
        self::assertSame(['_auth_user_id' => 42], $_SESSION);
    }

    public function testLoginCanReplaceTheAuthenticatedUser(): void
    {
        $auth = new Auth(new Session(false));

        $auth->login(42);
        $auth->login(84);

        self::assertSame(84, $auth->id());
    }

    public function testLogoutRemovesOnlyAuthenticationState(): void
    {
        $session = new Session(false);
        $auth = new Auth($session);
        $session->put('_csrf_token', 'csrf-value');
        $session->flash('status', 'preserved');
        $auth->login(42);

        $auth->logout();

        self::assertFalse($auth->check());
        self::assertNull($auth->id());
        self::assertSame('csrf-value', $session->get('_csrf_token'));
        self::assertSame(['status' => ['preserved']], $session->consumeFlash());
    }

    #[DataProvider('invalidUserIdProvider')]
    public function testRejectsNonPositiveUserIdsWithoutChangingAuthentication(int $invalidUserId): void
    {
        $auth = new Auth(new Session(false));
        $auth->login(42);

        try {
            $auth->login($invalidUserId);
            self::fail('A non-positive user ID should be rejected.');
        } catch (InvalidArgumentException) {
            self::assertSame(42, $auth->id());
        }
    }

    public static function invalidUserIdProvider(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
        ];
    }

    public function testInvalidStoredValueIsNotAuthenticated(): void
    {
        $_SESSION['_auth_user_id'] = '42';
        $auth = new Auth(new Session(false));

        self::assertFalse($auth->check());
        self::assertNull($auth->id());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLoginAndLogoutRegenerateActiveSessionWhilePreservingCsrfAndFlash(): void
    {
        $session = new Session();
        $session->start([
            'use_cookies' => false,
            'cache_limiter' => '',
        ]);
        $auth = new Auth($session);
        $csrf = new Csrf($session);
        $csrfToken = $csrf->token();
        $session->flash('status', 'preserved');
        $beforeLogin = session_id();

        $auth->login(42);
        $afterLogin = session_id();

        self::assertNotSame($beforeLogin, $afterLogin);
        self::assertSame(42, $auth->id());
        self::assertTrue($csrf->verify($csrfToken));

        $auth->logout();
        $afterLogout = session_id();

        self::assertNotSame($afterLogin, $afterLogout);
        self::assertFalse($auth->check());
        self::assertTrue($csrf->verify($csrfToken));
        self::assertSame(['status' => ['preserved']], $session->consumeFlash());
    }
}
