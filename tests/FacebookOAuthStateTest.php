<?php

declare(strict_types=1);

namespace Tests;

use App\Authentication\FacebookOAuthState;
use App\Core\Session;
use PHPUnit\Framework\TestCase;

final class FacebookOAuthStateTest extends TestCase
{
    protected function setUp(): void { $_SESSION = []; }
    protected function tearDown(): void { unset($_SESSION); }

    public function testIssuesOpaqueOneTimeLoginStateWithoutStoringRawToken(): void
    {
        $state = new FacebookOAuthState(new Session(false), static fn (): int => 1000);
        $token = $state->issue('login');

        self::assertSame(64, strlen($token));
        self::assertNotSame($token, $_SESSION['_facebook_oauth_state']['token_hash']);
        self::assertSame(['intent' => 'login', 'user_id' => null], $state->consume($token));
        self::assertNull($state->consume($token));
    }

    public function testLinkStateBindsAuthenticatedUserAndExpires(): void
    {
        $now = 1000;
        $state = new FacebookOAuthState(new Session(false), static function () use (&$now): int { return $now; });
        $token = $state->issue('link', 42);
        $now = 1601;

        self::assertNull($state->consume($token));
        self::assertSame([], $_SESSION);
    }

    public function testRejectsTamperedStateAndConsumesStoredAttempt(): void
    {
        $state = new FacebookOAuthState(new Session(false), static fn (): int => 1000);
        $state->issue('login');

        self::assertNull($state->consume(str_repeat('a', 64)));
        self::assertSame([], $_SESSION);
    }
}
