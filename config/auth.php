<?php

declare(strict_types=1);

$appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');

return [
    'google' => [
        'client_id' => trim((string) env('GOOGLE_CLIENT_ID', '')),
        'login_uri' => $appUrl . '/auth/google',
    ],
];
