<?php

declare(strict_types=1);

namespace Tests;

use App\Authentication\GoogleIdentity;
use App\Authentication\PendingGoogleOnboarding;
use App\Core\Session;
use PHPUnit\Framework\TestCase;

final class PendingGoogleOnboardingTest extends TestCase
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

    public function testStoresOnlyRequiredVerifiedOnboardingData(): void
    {
        $pending = new PendingGoogleOnboarding(new Session(false), static fn (): int => 1000);
        $identity = new GoogleIdentity(
            'subject',
            'person@example.com',
            true,
            'Person',
            'https://example.test/avatar.jpg',
        );

        $pending->store($identity);
        $restored = $pending->current();

        self::assertEquals($identity, $restored);
        self::assertArrayNotHasKey('credential', $_SESSION['_pending_google_onboarding']);
        self::assertArrayNotHasKey('id_token', $_SESSION['_pending_google_onboarding']);
    }

    public function testExpiresAndClearsPendingStateAfterTenMinutes(): void
    {
        $now = 1000;
        $pending = new PendingGoogleOnboarding(
            new Session(false),
            static function () use (&$now): int {
                return $now;
            },
        );
        $pending->store(new GoogleIdentity('subject', null, false, null, null));
        $now = 1601;

        self::assertNull($pending->current());
        self::assertSame([], $_SESSION);
    }

    public function testRejectsMalformedPendingState(): void
    {
        $_SESSION['_pending_google_onboarding'] = [
            'provider' => 'google',
            'provider_user_id' => '',
            'created_at' => 1000,
        ];
        $pending = new PendingGoogleOnboarding(new Session(false), static fn (): int => 1000);

        self::assertNull($pending->current());
        self::assertSame([], $_SESSION);
    }
}
