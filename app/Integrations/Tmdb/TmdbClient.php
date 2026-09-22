<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb;

use Closure;

final class TmdbClient implements TmdbCatalog
{
    private readonly Closure $http;

    public function __construct(
        private readonly string $readAccessToken,
        private readonly string $apiBaseUrl = 'https://api.themoviedb.org/3',
        private readonly string $language = 'pt-BR',
        private readonly string $region = 'BR',
        private readonly int $connectTimeout = 5,
        private readonly int $timeout = 10,
        private readonly int $maxResponseBytes = 2097152,
        ?Closure $http = null,
    ) {
        if ($apiBaseUrl !== 'https://api.themoviedb.org/3') {
            throw new \InvalidArgumentException('TMDB API base URL is not allowed.');
        }
        $this->http = $http ?? $this->defaultHttp(...);
    }

    public function configured(): bool
    {
        return trim($this->readAccessToken) !== '';
    }

    public function configuration(): TmdbImageConfiguration
    {
        return TmdbImageConfiguration::fromPayload($this->request('/configuration'));
    }

    public function movieDetails(int $id): TmdbMediaDetails
    {
        return $this->details($id, 'movie');
    }

    public function seriesDetails(int $id): TmdbMediaDetails
    {
        return $this->details($id, 'series');
    }

    public function seasonDetails(int $seriesId, int $seasonNumber): TmdbSeasonDetails
    {
        if (
            $seriesId < 1
            || $seriesId > 2147483647
            || $seasonNumber < 0
            || $seasonNumber > 2147483647
        ) {
            throw new \InvalidArgumentException('TMDB season identifiers are invalid.');
        }

        $payload = $this->request('/tv/' . $seriesId . '/season/' . $seasonNumber, [
            'language' => $this->language,
        ]);
        $details = (new TmdbSeasonDetailsNormalizer())->normalize($payload, $seriesId);

        if ($details === null || $details->seasonNumber !== $seasonNumber) {
            throw new TmdbException('unexpected_payload', 200);
        }

        return $details;
    }

    /** @return array{page:int,total_pages:int,total_results:int,results:list<TmdbMedia>} */
    public function searchMovies(string $query, int $page = 1): array
    {
        return $this->search('/search/movie', $query, $page, 'movie', true);
    }

    /** @return array{page:int,total_pages:int,total_results:int,results:list<TmdbMedia>} */
    public function searchSeries(string $query, int $page = 1): array
    {
        return $this->search('/search/tv', $query, $page, 'series', false);
    }

    public function discoverAnimeMovies(int $page = 1): array
    {
        return $this->discover('/discover/movie', $page, 'movie');
    }

    public function discoverAnimeSeries(int $page = 1): array
    {
        return $this->discover('/discover/tv', $page, 'series');
    }

    private function request(string $path, array $query = []): array
    {
        if (!$this->configured()) {
            throw new TmdbException('not_configured');
        }
        if (!$this->isSupportedPath($path)) {
            throw new \InvalidArgumentException('Unsupported TMDB path.');
        }

        $url = $this->apiBaseUrl . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        $headers = ['Accept: application/json', 'Authorization: Bearer ' . trim($this->readAccessToken)];

        try {
            $response = ($this->http)($url, $headers, $this->connectTimeout, $this->timeout, $this->maxResponseBytes);
        } catch (TmdbException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new TmdbException('transport');
        }

        if (!is_array($response)) {
            throw new TmdbException('transport');
        }
        if (($response['too_large'] ?? false) === true) {
            throw new TmdbException('response_too_large');
        }
        if (($response['transport_error'] ?? false) === true) {
            throw new TmdbException('transport');
        }

        $status = $response['status'] ?? null;
        $body = $response['body'] ?? null;
        if (!is_int($status) || !is_string($body)) {
            throw new TmdbException('unexpected_payload');
        }
        if (strlen($body) > $this->maxResponseBytes) {
            throw new TmdbException('response_too_large');
        }
        if ($status < 200 || $status >= 300) {
            throw $this->httpException($status, $response['headers'] ?? []);
        }

        try {
            $payload = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new TmdbException('invalid_json', $status);
        }
        if (!is_array($payload) || array_is_list($payload)) {
            throw new TmdbException('unexpected_payload', $status);
        }

        return $payload;
    }

    private function isSupportedPath(string $path): bool
    {
        if (in_array($path, ['/configuration', '/search/movie', '/search/tv', '/discover/movie', '/discover/tv'], true)) {
            return true;
        }
        if (preg_match('#^/(?:movie|tv)/([1-9]\d{0,9})$#D', $path, $matches) === 1) {
            return (int) $matches[1] <= 2147483647;
        }

        if (preg_match('#^/tv/([1-9]\d{0,9})/season/(0|[1-9]\d{0,9})$#D', $path, $matches) === 1) {
            return (int) $matches[1] <= 2147483647 && (int) $matches[2] <= 2147483647;
        }

        return false;
    }

    private function httpException(int $status, mixed $headers): TmdbException
    {
        $category = match (true) {
            $status === 401 => 'unauthorized',
            $status === 403 => 'forbidden',
            $status === 404 => 'not_found',
            $status === 429 => 'rate_limited',
            $status >= 500 => 'server_error',
            default => 'http_error',
        };

        $retryAfter = null;
        if ($status === 429 && is_array($headers)) {
            $value = $headers['retry-after'] ?? $headers['Retry-After'] ?? null;
            if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
                $retryAfter = min((int) $value, 86400);
            }
        }
        return new TmdbException($category, $status, $retryAfter);
    }

    /** @return array{page:int,total_pages:int,total_results:int,results:list<TmdbMedia>} */
    private function search(string $path, string $query, int $page, string $type, bool $withRegion): array
    {
        $query = trim($query);
        if ($query === '' || $page < 1) {
            throw new \InvalidArgumentException('Search query and page are invalid.');
        }

        $parameters = [
            'query' => $query,
            'language' => $this->language,
            'include_adult' => 'false',
            'page' => $page,
        ];
        if ($withRegion) {
            $parameters['region'] = $this->region;
        }
        return $this->normalizeMediaPage($this->request($path, $parameters), $type);
    }

    /** @return array{page:int,total_pages:int,total_results:int,results:list<TmdbMedia>} */
    private function discover(string $path, int $page, string $type): array
    {
        if ($page < 1 || $page > 500 || !in_array($type, ['movie', 'series'], true)) {
            throw new \InvalidArgumentException('Discover page or type is invalid.');
        }

        return $this->normalizeMediaPage($this->request($path, [
            'language' => $this->language,
            'include_adult' => 'false',
            'page' => $page,
            'with_genres' => (string) TmdbAnimeClassifier::ANIMATION_GENRE_ID,
            'with_original_language' => 'ja',
            'sort_by' => 'popularity.desc',
        ]), $type);
    }

    /** @return array{page:int,total_pages:int,total_results:int,results:list<TmdbMedia>} */
    private function normalizeMediaPage(array $payload, string $type): array
    {
        if (!is_int($payload['page'] ?? null) || $payload['page'] < 1
            || !is_int($payload['total_pages'] ?? null) || $payload['total_pages'] < 0
            || !is_int($payload['total_results'] ?? null) || $payload['total_results'] < 0
            || !is_array($payload['results'] ?? null)) {
            throw new TmdbException('unexpected_payload', 200);
        }

        $normalizer = new TmdbMediaNormalizer();
        $results = [];
        foreach ($payload['results'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $media = $type === 'movie' ? $normalizer->movie($item) : $normalizer->series($item);
            if ($media !== null) {
                $results[] = $media;
            }
        }

        return [
            'page' => $payload['page'],
            'total_pages' => $payload['total_pages'],
            'total_results' => $payload['total_results'],
            'results' => $results,
        ];
    }

    private function details(int $id, string $type): TmdbMediaDetails
    {
        if ($id < 1 || $id > 2147483647 || !in_array($type, ['movie', 'series'], true)) {
            throw new \InvalidArgumentException('TMDB details ID or type is invalid.');
        }

        $path = $type === 'movie' ? '/movie/' . $id : '/tv/' . $id;
        $payload = $this->request($path, ['language' => $this->language]);
        $normalizer = new TmdbMediaDetailsNormalizer();
        $details = $type === 'movie' ? $normalizer->movie($payload) : $normalizer->series($payload);
        if ($details === null || $details->sourceId !== $id) {
            throw new TmdbException('unexpected_payload', 200);
        }
        return $details;
    }

    private function defaultHttp(string $url, array $headers, int $connectTimeout, int $timeout, int $maxBytes): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            return ['transport_error' => true];
        }

        $body = '';
        $responseHeaders = [];
        $tooLarge = false;
        curl_setopt_array($handle, [
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$tooLarge, $maxBytes): int {
                if (strlen($body) + strlen($chunk) > $maxBytes) {
                    $tooLarge = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $separator = strpos($line, ':');
                if ($separator !== false) {
                    $name = strtolower(trim(substr($line, 0, $separator)));
                    if ($name === 'retry-after') {
                        $responseHeaders[$name] = trim(substr($line, $separator + 1));
                    }
                }
                return strlen($line);
            },
        ]);

        $ok = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        if ($tooLarge) {
            return ['too_large' => true];
        }
        if ($ok === false || curl_errno($handle) !== 0) {
            return ['transport_error' => true];
        }

        return ['status' => $status, 'body' => $body, 'headers' => $responseHeaders];
    }
}
