<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb;

use Closure;

final class FileTmdbPayloadCache implements TmdbPayloadCache
{
    private readonly Closure $now;
    private bool $circuitOpen = false;

    public function __construct(
        private readonly string $directory,
        private readonly int $maxPayloadBytes = 2097152,
        ?Closure $now = null,
    ) {
        $this->now = $now ?? static fn (): int => time();
    }

    public function remember(string $path, array $query, TmdbCacheProfile $profile, Closure $loader): array
    {
        $now = ($this->now)();
        $file = $this->file($path, $query);
        $entry = $this->read($file);
        if ($entry !== null && $entry['fresh_until'] >= $now) {
            return $entry['payload'];
        }
        if ($this->circuitOpen) {
            if ($entry !== null && $entry['stale_until'] >= $now) {
                return $entry['payload'];
            }
            throw new TmdbException('transport');
        }

        try {
            $payload = $loader();
        } catch (TmdbException $exception) {
            if ($this->isTransient($exception)) {
                $this->circuitOpen = true;
                if ($entry !== null && $entry['stale_until'] >= $now) {
                    return $entry['payload'];
                }
            }
            throw $exception;
        }

        $this->write($file, $payload, $now + $profile->freshSeconds(), $now + $profile->staleSeconds());
        return $payload;
    }

    /** @param array<string, scalar> $query */
    public function key(string $path, array $query): string
    {
        ksort($query, SORT_STRING);
        return hash('sha256', $path . "\n" . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
    }

    /** @param array<string, scalar> $query */
    private function file(string $path, array $query): string
    {
        return rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . $this->key($path, $query) . '.json';
    }

    /** @return null|array{fresh_until:int,stale_until:int,payload:array<string,mixed>} */
    private function read(string $file): ?array
    {
        if (!is_file($file)) {
            return null;
        }
        $size = @filesize($file);
        if (!is_int($size) || $size < 2 || $size > $this->maxPayloadBytes + 1024) {
            return null;
        }
        $json = @file_get_contents($file);
        if (!is_string($json)) {
            return null;
        }
        try {
            $entry = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($entry) || !is_int($entry['fresh_until'] ?? null)
            || !is_int($entry['stale_until'] ?? null) || !is_array($entry['payload'] ?? null)
            || array_is_list($entry['payload'])) {
            return null;
        }
        return $entry;
    }

    /** @param array<string,mixed> $payload */
    private function write(string $file, array $payload, int $freshUntil, int $staleUntil): void
    {
        try {
            $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (strlen($payloadJson) > $this->maxPayloadBytes) {
                return;
            }
            $json = json_encode([
                'fresh_until' => $freshUntil,
                'stale_until' => $staleUntil,
                'payload' => $payload,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_dir($this->directory) && !@mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
                return;
            }
            $temporary = $file . '.' . bin2hex(random_bytes(8)) . '.tmp';
            if (@file_put_contents($temporary, $json, LOCK_EX) === false) {
                return;
            }
            if (!@rename($temporary, $file)) {
                @unlink($temporary);
            }
        } catch (\Throwable) {
            // Cache is an optimization and must always fail open.
        }
    }

    private function isTransient(TmdbException $exception): bool
    {
        return in_array($exception->category, ['transport', 'server_error', 'rate_limited'], true);
    }
}
