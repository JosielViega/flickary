<?php

declare(strict_types=1);

namespace Tests;

use App\Authentication\GoogleApiIdentityVerifier;
use PHPUnit\Framework\TestCase;

final class GoogleApiIdentityVerifierTest extends TestCase
{
    public function testMapsOnlyAcceptedClaimsAfterLibraryVerification(): void
    {
        $verifier = new GoogleApiIdentityVerifier('client-id', static fn (string $credential): array => [
            'sub' => 'google-subject-123',
            'email' => 'Person@Example.COM',
            'email_verified' => true,
            'name' => "  Person\x00 Name  ",
            'picture' => 'https://images.example.test/avatar.jpg',
            'ignored_claim' => 'not persisted',
        ]);

        $identity = $verifier->verify('signed-id-token');

        self::assertNotNull($identity);
        self::assertSame('google-subject-123', $identity->subject);
        self::assertSame('person@example.com', $identity->email);
        self::assertTrue($identity->emailVerified);
        self::assertSame('Person Name', $identity->displayName);
        self::assertSame('https://images.example.test/avatar.jpg', $identity->avatarUrl);
    }

    public function testIgnoresUnverifiedEmailAndNonHttpsAvatar(): void
    {
        $verifier = new GoogleApiIdentityVerifier('client-id', static fn (): array => [
            'sub' => 'subject',
            'email' => 'person@example.com',
            'email_verified' => false,
            'picture' => 'http://images.example.test/avatar.jpg',
        ]);

        $identity = $verifier->verify('credential');

        self::assertNotNull($identity);
        self::assertNull($identity->email);
        self::assertFalse($identity->emailVerified);
        self::assertNull($identity->avatarUrl);
    }

    public function testRejectsPayloadWithoutNonEmptySubject(): void
    {
        $verifier = new GoogleApiIdentityVerifier('client-id', static fn (): array => ['sub' => '']);

        self::assertNull($verifier->verify('credential'));
    }

    public function testTreatsVerificationFailureAsInvalidCredential(): void
    {
        $verifier = new GoogleApiIdentityVerifier('client-id', static function (): never {
            throw new \UnexpectedValueException('invalid signature');
        });

        self::assertNull($verifier->verify('credential'));
    }

    public function testDoesNotInvokeLibraryWithoutConfiguration(): void
    {
        $called = false;
        $verifier = new GoogleApiIdentityVerifier('', static function () use (&$called): array {
            $called = true;
            return ['sub' => 'subject'];
        });

        self::assertNull($verifier->verify('credential'));
        self::assertFalse($called);
    }
}
