<?php

declare(strict_types=1);

$requestStartedAt = hrtime(true);

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$router = require dirname(__DIR__) . '/routes/web.php';

$response = $app['account_suspension_guard']->intercept($app['request']) ?? $router->dispatch($app['request']);
$durationMilliseconds = (hrtime(true) - $requestStartedAt) / 1_000_000;
$response->withHeader('Server-Timing', 'app;dur=' . number_format($durationMilliseconds, 1, '.', ''))->send();
