<?php

declare(strict_types=1);

namespace Tests;

use App\Authentication\ExternalIdentity;
use App\Authentication\PendingExternalOnboarding;
use App\Core\Session;
use PHPUnit\Framework\TestCase;

final class PendingExternalOnboardingTest extends TestCase
{
    protected function setUp(): void { $_SESSION = []; }
    protected function tearDown(): void { unset($_SESSION); }

    public function testStoresOnlyRequiredOnboardingDataForEitherProvider(): void
    {
        $pending = new PendingExternalOnboarding(new Session(false), static fn (): int => 1000);
        $identity = new ExternalIdentity('google', 'subject', 'person@example.com', true, 'Person', 'https://example.test/avatar.jpg');

        $pending->store($identity);

        self::assertEquals($identity, $pending->current());
        self::assertArrayNotHasKey('credential', $_SESSION['_pending_external_onboarding']);
        self::assertArrayNotHasKey('id_token', $_SESSION['_pending_external_onboarding']);
        self::assertArrayNotHasKey('access_token', $_SESSION['_pending_external_onboarding']);
    }

    public function testExpiresAndClearsPendingStateAfterTenMinutes(): void
    {
        $now = 1000;
        $pending = new PendingExternalOnboarding(new Session(false), static function () use (&$now): int { return $now; });
        $pending->store(new ExternalIdentity('facebook', 'subject', null, false, null, null));
        $now = 1601;

        self::assertNull($pending->current());
        self::assertSame([], $_SESSION);
    }

    public function testRejectsMalformedPendingState(): void
    {
        $_SESSION['_pending_external_onboarding'] = ['provider' => 'google', 'provider_user_id' => '', 'created_at' => 1000];
        $pending = new PendingExternalOnboarding(new Session(false), static fn (): int => 1000);

        self::assertNull($pending->current());
        self::assertSame([], $_SESSION);
    }
}
