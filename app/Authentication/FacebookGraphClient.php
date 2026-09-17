<?php

declare(strict_types=1);

namespace App\Authentication;

use Closure;

final class FacebookGraphClient implements FacebookIdentityProvider
{
    private const MAX_RESPONSE_BYTES = 1048576;

    private readonly Closure $http;

    public function __construct(
        private readonly string $appId,
        private readonly string $appSecret,
        private readonly string $redirectUri,
        private readonly string $graphVersion,
        ?Closure $http = null,
    ) {
        $this->http = $http ?? $this->defaultHttp(...);
    }

    public function configured(): bool
    {
        return $this->appId !== '' && $this->appSecret !== '' && $this->redirectUri !== ''
            && preg_match('/^v\d+\.\d+$/', $this->graphVersion) === 1;
    }

    public function authorizationUrl(string $state): string
    {
        if (!$this->configured()) {
            throw new \RuntimeException('Facebook Login is not configured.');
        }

        return 'https://www.facebook.com/' . rawurlencode($this->graphVersion) . '/dialog/oauth?'
            . http_build_query([
                'client_id' => $this->appId,
                'redirect_uri' => $this->redirectUri,
                'state' => $state,
                'response_type' => 'code',
            ], '', '&', PHP_QUERY_RFC3986);
    }

    public function identityFromCode(string $code): ?ExternalIdentity
    {
        if (!$this->configured() || trim($code) === '' || strlen($code) > 4096) {
            return null;
        }

        try {
            $tokenResponse = ($this->http)('POST', $this->graphUrl('/oauth/access_token'), [
                'client_id' => $this->appId,
                'client_secret' => $this->appSecret,
                'redirect_uri' => $this->redirectUri,
                'code' => $code,
            ], []);
            $tokenPayload = $this->payload($tokenResponse);
            $token = $tokenPayload['access_token'] ?? null;
            if (!is_string($token) || $token === '' || strlen($token) > 8192) {
                return null;
            }

            $profileResponse = ($this->http)('GET', $this->graphUrl('/me') . '?'
                . http_build_query([
                    'fields' => 'id,name,picture',
                    'appsecret_proof' => hash_hmac('sha256', $token, $this->appSecret),
                ], '', '&', PHP_QUERY_RFC3986), [], ['Authorization: Bearer ' . $token]);
            $profile = $this->payload($profileResponse);
        } catch (\Throwable) {
            return null;
        }

        $id = $profile['id'] ?? null;
        if (!is_string($id) || trim($id) === '' || strlen($id) > 255) {
            return null;
        }

        return new ExternalIdentity(
            'facebook',
            trim($id),
            null,
            false,
            $this->displayName($profile['name'] ?? null),
            $this->pictureUrl($profile['picture'] ?? null),
        );
    }

    private function graphUrl(string $path): string
    {
        return 'https://graph.facebook.com/' . rawurlencode($this->graphVersion) . $path;
    }

    private function payload(mixed $response): array
    {
        if (!is_array($response) || ($response['status'] ?? 0) < 200 || ($response['status'] ?? 0) >= 300
            || !is_string($response['body'] ?? null) || strlen($response['body']) > self::MAX_RESPONSE_BYTES) {
            throw new \RuntimeException('Unexpected Facebook response.');
        }

        $payload = json_decode($response['body'], true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($payload) || isset($payload['error'])) {
            throw new \RuntimeException('Facebook returned an error.');
        }

        return $payload;
    }

    private function displayName(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $value));
        return $name === '' ? null : mb_substr($name, 0, 100);
    }

    private function pictureUrl(mixed $picture): ?string
    {
        $url = is_array($picture) && is_array($picture['data'] ?? null) ? ($picture['data']['url'] ?? null) : null;
        if (!is_string($url) || strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https' ? $url : null;
    }

    private function defaultHttp(string $method, string $url, array $form, array $headers): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('Unable to initialize HTTP client.');
        }

        $body = '';
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ['Accept: application/json', ...$headers],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > self::MAX_RESPONSE_BYTES) {
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        if ($method === 'POST') {
            curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($form, '', '&', PHP_QUERY_RFC3986));
            curl_setopt($handle, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded', ...$headers]);
        }

        $ok = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if ($ok === false || $error !== '') {
            throw new \RuntimeException('Facebook request failed.');
        }

        return ['status' => $status, 'body' => $body];
    }
}
