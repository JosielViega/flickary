<?php

declare(strict_types=1);

use App\Controllers\GoogleAuthController;
use App\Controllers\HealthController;
use App\Controllers\HomeController;
use App\Controllers\LoginController;
use App\Controllers\LogoutController;
use App\Controllers\OnboardingController;
use App\Controllers\ProfileController;
use App\Core\Request;
use App\Core\Response;

$home = new HomeController(
    $app['view'],
    $app['config'],
    $app['auth'],
    $app['csrf'],
);
$health = new HealthController();
$login = new LoginController(
    $app['view'],
    $app['auth'],
    $app['session'],
    $app['auth_config']['google'],
);
$googleAuth = new GoogleAuthController(
    $app['auth'],
    $app['session'],
    $app['google_identity_verifier'],
    $app['external_identities'],
    $app['pending_google_onboarding'],
    $app['auth_config']['google']['client_id'],
);
$onboarding = new OnboardingController(
    $app['view'],
    $app['auth'],
    $app['csrf'],
    $app['session'],
    $app['pending_google_onboarding'],
    $app['username_policy'],
    $app['account_creator'],
);
$logout = new LogoutController($app['auth'], $app['csrf']);
$profile = new ProfileController(
    $app['view'],
    $app['auth'],
    $app['csrf'],
    $app['session'],
    $app['profiles'],
);
$router = $app['router'];

$router->get('/', [$home, 'index']);
$router->get('/login', [$login, 'show']);
$router->post('/auth/google', static fn (): Response => $googleAuth->handle($app['request']));
$router->get('/onboarding/username', [$onboarding, 'show']);
$router->post('/onboarding/username', static fn (): Response => $onboarding->store($app['request']));
$router->post('/logout', static fn (): Response => $logout->handle($app['request']));
$router->get('/perfil', [$profile, 'show']);
$router->post('/perfil', static fn (): Response => $profile->update($app['request']));
$router->get('/health', [$health, 'index']);
$router->fallback(static function (Request $request) use ($app): Response {
    return Response::html($app['view']->render('pages/404', [
        'title' => 'Página não encontrada',
        'path' => $request->path(),
        'currentRoute' => null,
        'auth' => $app['auth'],
        'csrf' => $app['csrf'],
    ]), 404);
});

return $router;
