<?php

declare(strict_types=1);

$appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');

return [
    'google' => [
        'client_id' => trim((string) env('GOOGLE_CLIENT_ID', '')),
        'login_uri' => $appUrl . '/auth/google',
    ],
    'facebook' => [
        'app_id' => trim((string) env('FACEBOOK_APP_ID', '')),
        'app_secret' => trim((string) env('FACEBOOK_APP_SECRET', '')),
        'redirect_uri' => $appUrl . '/auth/facebook/callback',
        'graph_version' => 'v26.0',
    ],
];
