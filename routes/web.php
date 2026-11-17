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
use App\Controllers\SeriesSeasonController;
use App\Controllers\WatchHistoryController;
use App\Controllers\UserScheduleController;
use App\Controllers\StatisticsController;
use App\Controllers\AnimeController;
use App\Controllers\UserMediaPreferenceController;
use App\Controllers\PublicProfileController;
use App\Controllers\FollowController;
use App\Controllers\FollowRequestController;
use App\Controllers\UserSearchController;
use App\Controllers\SocialFeedController;
use App\Controllers\SocialActivityController;
use App\Controllers\ProfileCommentsController;
use App\Controllers\NotificationController;
use App\Controllers\DirectMessageController;
use App\Controllers\SocialControlController;
use App\Social\NullUserSocialControlStore;
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
    $app['profile_updater'],
    $app['user_follow_requests'],
);
$search = new SearchController(
    $app['view'],
    $app['tmdb'],
    $app['auth'],
    $app['csrf'],
);
$anime = new AnimeController($app['view'], $app['tmdb'], $app['auth'], $app['csrf']);
$mediaDetails = new MediaDetailsController(
    $app['view'],
    $app['tmdb'],
    $app['auth'],
    $app['csrf'],
    $app['user_media'],
    $app['session'],
    $app['series_progress'],
    $app['user_schedule'],
    $app['media_preferences'],
    publicUrl: $app['public_url'],
);
$userMedia = new UserMediaController(
    $app['view'],
    $app['auth'],
    $app['csrf'],
    $app['session'],
    $app['user_media'],
    $app['tmdb'],
);
$mediaPreferences = new UserMediaPreferenceController(
    $app['view'],
    $app['auth'],
    $app['csrf'],
    $app['session'],
    $app['media_preferences'],
    $app['tmdb'],
);
$season = new SeriesSeasonController(
    $app['view'],
    $app['tmdb'],
    $app['auth'],
    $app['csrf'],
    $app['session'],
    $app['user_media'],
    $app['series_progress'],
    $app['user_schedule'],
);
$history = new WatchHistoryController(
    $app['view'],
    $app['auth'],
    $app['csrf'],
    $app['session'],
    $app['watch_history'],
    $app['tmdb'],
);
$schedule = new UserScheduleController(
    $app['view'], $app['auth'], $app['csrf'], $app['session'], $app['user_schedule'], $app['tmdb'],
);
$statistics = new StatisticsController(
    $app['view'], $app['auth'], $app['csrf'], $app['personal_statistics'],
);
$about = new AboutController(
    $app['view'],
    $app['auth'],
    $app['csrf'],
);
$socialControlStore=$app['social_controls']??new NullUserSocialControlStore();
$publicProfile = new PublicProfileController(
    $app['view'], $app['auth'], $app['csrf'], $app['session'], $app['username_policy'],
    $app['public_profiles'], $app['user_follows'], $app['tmdb'], $app['public_url'],
    $app['user_follow_requests'], $app['private_profile_access_policy'], $app['direct_messages'],
    $socialControlStore,
);
$followRequestStore = $app['user_follow_requests'];
$follow = new FollowController(
    $app['view'], $app['auth'], $app['csrf'], $app['session'], $app['username_policy'],
    $app['public_profiles'], $app['user_follows'], $followRequestStore,$socialControlStore,
);
$followRequests = new FollowRequestController(
    $app['view'], $app['auth'], $app['csrf'], $app['session'], $app['username_policy'],
    $app['public_profiles'], $followRequestStore,$socialControlStore,
);
$userSearch = new UserSearchController($app['view'], $app['auth'], $app['csrf'], $app['public_profiles']);
$socialFeed = new SocialFeedController(
    $app['view'], $app['auth'], $app['csrf'], $app['social_feed'], $app['user_follows'],
    $app['social_activity_likes'], $app['tmdb'],
);
$socialActivity = new SocialActivityController(
    $app['view'], $app['auth'], $app['csrf'], $app['social_activity_comments'],
    $app['social_activity_comments'], $app['tmdb'],
);
$profileComments = new ProfileCommentsController(
    $app['view'], $app['auth'], $app['csrf'], $app['social_activity_comments'],
);
$notifications = new NotificationController(
    $app['view'], $app['auth'], $app['csrf'], $app['social_notifications'], $app['social_notifications'],
);
$directMessages = new DirectMessageController(
    $app['view'], $app['auth'], $app['csrf'], $app['username_policy'], $app['public_profiles'],
    $app['user_follows'], $app['direct_messages'], $app['direct_messages'],
    $socialControlStore,
);
$socialControls=new SocialControlController($app['view'],$app['auth'],$app['csrf'],$app['session'],$app['username_policy'],$app['public_profiles'],$socialControlStore);
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
$router->get('/usuarios', static fn (): Response => $userSearch->index($app['request']));
$router->get('/social', static fn (): Response => $socialFeed->index($app['request']));
$router->get('/notificacoes', static fn (): Response => $notifications->index($app['request']));
$router->post('/notificacoes/ler-todas', static fn (): Response => $notifications->markAllRead($app['request']));
$router->post('/notificacoes/{id}/abrir', static fn (string $id): Response => $notifications->open($app['request'], $id));
$router->get('/mensagens', static fn (): Response => $directMessages->index($app['request']));
$router->get('/mensagens/com/{username}', static fn (string $username): Response => $directMessages->show($app['request'], $username));
$router->post('/mensagens/com/{username}', static fn (string $username): Response => $directMessages->send($app['request'], $username));
$router->post('/mensagens/com/{username}/ler', static fn (string $username): Response => $directMessages->markRead($app['request'], $username));
$router->post('/mensagens/com/{username}/abrir', static fn (string $username): Response => $directMessages->open($app['request'], $username));
$router->get('/social/atividades/{id}', static fn (string $id): Response => $socialActivity->show($app['request'], $id));
$router->post('/social/atividades/{id}/comentarios', static fn (string $id): Response => $socialActivity->createComment($app['request'], $id));
$router->post('/social/comentarios/{id}/remover', static fn (string $id): Response => $socialActivity->deleteComment($app['request'], $id));
$router->post('/social/atividades/{id}/curtir', static fn (string $id): Response => $socialFeed->like($app['request'], $id));
$router->post('/social/atividades/{id}/descurtir', static fn (string $id): Response => $socialFeed->unlike($app['request'], $id));
$router->get('/u/{username}', static fn (string $username): Response => $publicProfile->show($username));
$router->get('/u/{username}/seguidores', static fn (string $username): Response => $publicProfile->followers($app['request'], $username));
$router->get('/u/{username}/seguindo', static fn (string $username): Response => $publicProfile->following($app['request'], $username));
$router->post('/u/{username}/seguir', static fn (string $username): Response => $follow->follow($app['request'], $username));
$router->post('/u/{username}/cancelar-solicitacao', static fn (string $username): Response => $follow->cancel($app['request'], $username));
$router->post('/u/{username}/deixar-de-seguir', static fn (string $username): Response => $follow->unfollow($app['request'], $username));
$router->post('/u/{username}/bloquear',static fn(string$username):Response=>$socialControls->block($app['request'],$username));
$router->post('/u/{username}/silenciar',static fn(string$username):Response=>$socialControls->mute($app['request'],$username));
$router->post('/u/{username}/dessilenciar',static fn(string$username):Response=>$socialControls->unmute($app['request'],$username));
$router->get('/perfil/bloqueados',static fn():Response=>$socialControls->blocked($app['request']));
$router->post('/perfil/bloqueados/{username}/desbloquear',static fn(string$username):Response=>$socialControls->unblock($app['request'],$username));
$router->get('/perfil/silenciados',static fn():Response=>$socialControls->muted($app['request']));
$router->get('/perfil/solicitacoes', static fn (): Response => $followRequests->index($app['request']));
$router->get('/perfil/comentarios', static fn (): Response => $profileComments->index($app['request']));
$router->post('/perfil/solicitacoes/{username}/aceitar', static fn (string $username): Response => $followRequests->accept($app['request'], $username));
$router->post('/perfil/solicitacoes/{username}/recusar', static fn (string $username): Response => $followRequests->reject($app['request'], $username));
$router->get('/anime', static fn (): Response => $anime->index($app['request']));
$router->get('/filmes/{id}', static fn (string $id): Response => $mediaDetails->movie($id));
$router->get('/series/{id}', static fn (string $id): Response => $mediaDetails->series($id));
$router->get(
    '/series/{id}/temporadas/{season}',
    static fn (string $id, string $seasonNumber): Response => $season->show($id, $seasonNumber),
);
$router->post(
    '/series/{id}/temporadas/{season}/episodios/{episode}/assistido',
    static fn (string $id, string $seasonNumber, string $episode): Response =>
        $season->mark($app['request'], $id, $seasonNumber, $episode),
);
$router->post(
    '/series/{id}/temporadas/{season}/episodios/{episode}/desmarcar',
    static fn (string $id, string $seasonNumber, string $episode): Response =>
        $season->unmark($app['request'], $id, $seasonNumber, $episode),
);
$router->post(
    '/series/{id}/temporadas/{season}/assistidos',
    static fn (string $id, string $seasonNumber): Response =>
        $season->markSeason($app['request'], $id, $seasonNumber),
);
$router->post(
    '/series/{id}/temporadas/{season}/assistidos/remover',
    static fn (string $id, string $seasonNumber): Response =>
        $season->clearSeason($app['request'], $id, $seasonNumber),
);
$router->post('/filmes/{id}/lista', static fn (string $id): Response => $userMedia->save($app['request'], $id, 'movie'));
$router->post('/filmes/{id}/lista/remover', static fn (string $id): Response => $userMedia->remove($app['request'], $id, 'movie'));
$router->post('/filmes/{id}/historico', static fn (string $id): Response => $history->createMovie($app['request'], $id));
$router->post('/filmes/{id}/agenda', static fn (string $id): Response => $schedule->createMedia($app['request'], $id, 'movie'));
$router->post('/filmes/{id}/preferencias', static fn (string $id): Response => $mediaPreferences->save($app['request'], $id, 'movie'));
$router->post('/series/{id}/lista', static fn (string $id): Response => $userMedia->save($app['request'], $id, 'series'));
$router->post('/series/{id}/lista/remover', static fn (string $id): Response => $userMedia->remove($app['request'], $id, 'series'));
$router->post('/series/{id}/agenda', static fn (string $id): Response => $schedule->createMedia($app['request'], $id, 'series'));
$router->post('/series/{id}/preferencias', static fn (string $id): Response => $mediaPreferences->save($app['request'], $id, 'series'));
$router->post(
    '/series/{id}/temporadas/{season}/episodios/{episode}/historico',
    static fn (string $id, string $seasonNumber, string $episode): Response =>
        $history->createEpisode($app['request'], $id, $seasonNumber, $episode),
);
$router->post(
    '/series/{id}/temporadas/{season}/episodios/{episode}/agenda',
    static fn (string $id, string $seasonNumber, string $episode): Response =>
        $schedule->createEpisode($app['request'], $id, $seasonNumber, $episode),
);
$router->get('/minha-lista', static fn (): Response => $userMedia->index($app['request']));
$router->get('/favoritos', static fn (): Response => $mediaPreferences->favorites($app['request']));
$router->get('/avaliacoes', static fn (): Response => $mediaPreferences->ratings($app['request']));
$router->get('/historico', static fn (): Response => $history->index($app['request']));
$router->post('/historico/{id}', static fn (string $id): Response => $history->update($app['request'], $id));
$router->post('/historico/{id}/remover', static fn (string $id): Response => $history->remove($app['request'], $id));
$router->get('/agenda', static fn (): Response => $schedule->index($app['request']));
$router->post('/agenda/{id}', static fn (string $id): Response => $schedule->update($app['request'], $id));
$router->post('/agenda/{id}/remover', static fn (string $id): Response => $schedule->remove($app['request'], $id));
$router->get('/estatisticas', [$statistics, 'index']);
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
