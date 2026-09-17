<?php

declare(strict_types=1);

return [
    'read_access_token' => trim((string) env('TMDB_READ_ACCESS_TOKEN', '')),
    'api_base_url' => 'https://api.themoviedb.org/3',
    'language' => 'pt-BR',
    'region' => 'BR',
    'connect_timeout' => 5,
    'timeout' => 10,
    'max_response_bytes' => 2 * 1024 * 1024,
];
