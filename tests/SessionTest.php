<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Session;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
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

    public function testRegenerateDoesNothingBeforeSessionStarts(): void
    {
        self::assertSame(PHP_SESSION_NONE, session_status());

        (new Session(false))->regenerate();

        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRegenerateReplacesActiveIdAndPreservesSessionData(): void
    {
        $session = new Session();
        $session->start([
            'use_cookies' => false,
            'cache_limiter' => '',
        ]);
        $session->put('marker', 'preserved');
        $before = session_id();

        $session->regenerate();

        self::assertNotSame($before, session_id());
        self::assertSame('preserved', $session->get('marker'));
    }
}
