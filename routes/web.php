<?php

declare(strict_types=1);

use App\Controllers\AboutController;
use App\Controllers\GoogleAuthController;
use App\Controllers\FacebookAuthController;
use App\Controllers\FacebookConnectionController;
use App\Controllers\HealthController;
use App\Controllers\HomeController;
use App\Controllers\LoginController;
use App\Controllers\LogoutController;
use App\Controllers\MediaDetailsController;
use App\Controllers\OnboardingController;
use App\Controllers\ProfileController;
use App\Controllers\SearchController;
use App\Controllers\UserMediaController;
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
    $app['auth_config']['facebook'],
);
$googleAuth = new GoogleAuthController(
    $app['auth'],
    $app['session'],
    $app['google_identity_verifier'],
    $app['external_identities'],
    $app['pending_external_onboarding'],
    $app['auth_config']['google']['client_id'],
);
$facebookAuth = new FacebookAuthController(
    $app['auth'],
    $app['session'],
    $app['facebook_identity_provider'],
    $app['facebook_oauth_state'],
    $app['external_identities'],
    $app['external_identities'],
    $app['pending_external_onboarding'],
);
$facebookConnection = new FacebookConnectionController(
    $app['auth'],
    $app['csrf'],
    $app['session'],
    $app['facebook_identity_provider'],
    $app['facebook_oauth_state'],
    $app['external_identities'],
);
$onboarding = new OnboardingController(
    $app['view'],
    $app['auth'],
    $app['csrf'],
    $app['session'],
    $app['pending_external_onboarding'],
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
    $app['external_identities'],
    $app['facebook_identity_provider']->configured(),
);
$search = new SearchController(
    $app['view'],
    $app['tmdb'],
    $app['auth'],
    $app['csrf'],
);
$mediaDetails = new MediaDetailsController(
    $app['view'],
    $app['tmdb'],
    $app['auth'],
    $app['csrf'],
    $app['user_media'],
    $app['session'],
);
$userMedia = new UserMediaController(
    $app['view'],
    $app['auth'],
    $app['csrf'],
    $app['session'],
    $app['user_media'],
    $app['tmdb'],
);
$about = new AboutController(
    $app['view'],
    $app['auth'],
    $app['csrf'],
);
$router = $app['router'];

$router->get('/', [$home, 'index']);
$router->get('/login', [$login, 'show']);
$router->post('/auth/google', static fn (): Response => $googleAuth->handle($app['request']));
$router->get('/auth/facebook', [$facebookAuth, 'start']);
$router->get('/auth/facebook/callback', static fn (): Response => $facebookAuth->callback($app['request']));
$router->get('/onboarding/username', [$onboarding, 'show']);
$router->post('/onboarding/username', static fn (): Response => $onboarding->store($app['request']));
$router->post('/logout', static fn (): Response => $logout->handle($app['request']));
$router->get('/perfil', [$profile, 'show']);
$router->post('/perfil', static fn (): Response => $profile->update($app['request']));
$router->post('/perfil/conexoes/facebook', static fn (): Response => $facebookConnection->store($app['request']));
$router->get('/buscar', static fn (): Response => $search->index($app['request']));
$router->get('/filmes/{id}', static fn (string $id): Response => $mediaDetails->movie($id));
$router->get('/series/{id}', static fn (string $id): Response => $mediaDetails->series($id));
$router->post('/filmes/{id}/lista', static fn (string $id): Response => $userMedia->save($app['request'], $id, 'movie'));
$router->post('/filmes/{id}/lista/remover', static fn (string $id): Response => $userMedia->remove($app['request'], $id, 'movie'));
$router->post('/series/{id}/lista', static fn (string $id): Response => $userMedia->save($app['request'], $id, 'series'));
$router->post('/series/{id}/lista/remover', static fn (string $id): Response => $userMedia->remove($app['request'], $id, 'series'));
$router->get('/minha-lista', static fn (): Response => $userMedia->index($app['request']));
$router->get('/sobre', [$about, 'index']);
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
