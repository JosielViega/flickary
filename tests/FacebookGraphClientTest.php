<?php

declare(strict_types=1);

namespace Tests;

use App\Authentication\FacebookGraphClient;
use PHPUnit\Framework\TestCase;

final class FacebookGraphClientTest extends TestCase
{
    public function testUsesVersionedServerFlowAndAppSecretProofWithoutTrustingEmail(): void
    {
        $calls = [];
        $http = static function (string $method, string $url, array $form, array $headers) use (&$calls): array {
            $calls[] = compact('method', 'url', 'form', 'headers');
            return count($calls) === 1
                ? ['status' => 200, 'body' => '{"access_token":"transient-token"}']
                : ['status' => 200, 'body' => '{"id":"facebook-123","name":"Film Person","email":"ignored@example.com","picture":{"data":{"url":"https://example.test/avatar.jpg"}}}'];
        };
        $client = new FacebookGraphClient('app-id', 'app-secret', 'https://flickary.test/auth/facebook/callback', 'v26.0', $http);

        $identity = $client->identityFromCode('authorization-code');

        self::assertNotNull($identity);
        self::assertSame('facebook', $identity->provider);
        self::assertSame('facebook-123', $identity->providerUserId);
        self::assertNull($identity->email);
        self::assertFalse($identity->emailVerified);
        self::assertSame('Film Person', $identity->displayName);
        self::assertSame('POST', $calls[0]['method']);
        self::assertStringContainsString('/v26.0/oauth/access_token', $calls[0]['url']);
        self::assertStringNotContainsString('app-secret', $calls[0]['url']);
        self::assertSame('app-secret', $calls[0]['form']['client_secret']);
        self::assertStringContainsString('/v26.0/me?', $calls[1]['url']);
        self::assertStringContainsString(hash_hmac('sha256', 'transient-token', 'app-secret'), $calls[1]['url']);
        self::assertContains('Authorization: Bearer transient-token', $calls[1]['headers']);
    }

    public function testAuthorizationUrlIsVersionedAndCarriesExactRedirectAndState(): void
    {
        $client = new FacebookGraphClient('app-id', 'secret', 'https://flickary.test/auth/facebook/callback', 'v26.0', static fn (): array => []);
        $url = $client->authorizationUrl(str_repeat('a', 64));

        self::assertStringStartsWith('https://www.facebook.com/v26.0/dialog/oauth?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame('https://flickary.test/auth/facebook/callback', $query['redirect_uri']);
        self::assertSame(str_repeat('a', 64), $query['state']);
        self::assertSame('code', $query['response_type']);
    }

    public function testFailsClosedOnProviderErrorOrInvalidProfile(): void
    {
        $error = new FacebookGraphClient('id', 'secret', 'https://callback.test', 'v26.0', static fn (): array => [
            'status' => 400,
            'body' => '{"error":{"message":"no"}}',
        ]);
        self::assertNull($error->identityFromCode('code'));

        $call = 0;
        $invalid = new FacebookGraphClient('id', 'secret', 'https://callback.test', 'v26.0', static function () use (&$call): array {
            $call++;
            return $call === 1
                ? ['status' => 200, 'body' => '{"access_token":"token"}']
                : ['status' => 200, 'body' => '{"id":""}'];
        });
        self::assertNull($invalid->identityFromCode('code'));
    }
}
