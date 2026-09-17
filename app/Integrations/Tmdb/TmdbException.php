<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb;

final class TmdbException extends \RuntimeException
{
    public function __construct(
        public readonly string $category,
        public readonly ?int $httpStatus = null,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct(match ($category) {
            'not_configured' => 'TMDB integration is not configured.',
            'transport' => 'TMDB request failed.',
            'unauthorized', 'forbidden' => 'TMDB authentication failed.',
            'not_found' => 'TMDB resource was not found.',
            'rate_limited' => 'TMDB rate limit was reached.',
            'server_error' => 'TMDB service is unavailable.',
            'invalid_json' => 'TMDB returned invalid JSON.',
            'unexpected_payload' => 'TMDB returned an unexpected payload.',
            'response_too_large' => 'TMDB response exceeded the allowed size.',
            default => 'TMDB request was not successful.',
        });
    }
}
