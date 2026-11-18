<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\ErrorHandler;
use App\Core\Logger;
use App\Core\PublicUrl;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Core\CompositeLayoutContextProvider;
use App\Validation\Validator;
use App\Authentication\AccountOnboardingService;
use App\Authentication\GoogleApiIdentityVerifier;
use App\Authentication\FacebookGraphClient;
use App\Authentication\FacebookOAuthState;
use App\Authentication\PendingExternalOnboarding;
use App\Authentication\UsernamePolicy;
use App\Repositories\ExternalIdentityRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserMediaRepository;
use App\Repositories\UserSeriesProgressRepository;
use App\Repositories\WatchHistoryRepository;
use App\Repositories\UserScheduleRepository;
use App\Repositories\PersonalStatisticsRepository;
use App\Repositories\AnimeSnapshotRepository;
use App\Repositories\UserMediaPreferenceRepository;
use App\Repositories\PublicProfileRepository;
use App\Repositories\UserFollowRepository;
use App\Repositories\UserFollowRequestRepository;
use App\Repositories\SocialFeedRepository;
use App\Repositories\SocialActivityLikeRepository;
use App\Repositories\SocialActivityCommentRepository;
use App\Repositories\SocialNotificationRepository;
use App\Repositories\DirectMessageRepository;
use App\Repositories\UserSocialControlRepository;
use App\Repositories\AdminUserRepository;
use App\Repositories\SocialReportRepository;
use App\Repositories\AccountAccessRepository;
use App\Repositories\ModerationActionRepository;
use App\Moderation\AccountSuspensionGuard;
use App\Moderation\ModerationActionService;
use App\Notifications\NotificationLayoutContextProvider;
use App\Messaging\MessageLayoutContextProvider;
use App\Profiles\ProfileUpdateService;
use App\Social\PrivateProfileAccessPolicy;
use App\Integrations\Tmdb\TmdbClient;
use App\Integrations\Tmdb\FileTmdbPayloadCache;
use App\Discovery\ContentDiscoveryService;
use App\Navigation\PartialNavigationRequest;
use Dotenv\Dotenv;

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(500);
    exit('Dependencies are missing. Run composer install.');
}

require $autoload;

Dotenv::createImmutable($root)->safeLoad();

$appConfig = require $root . '/config/app.php';
$authConfig = require $root . '/config/auth.php';
$databaseConfig = require $root . '/config/database.php';
$tmdbConfig = require $root . '/config/tmdb.php';
$logger = new Logger($root . '/storage/logs');
(new ErrorHandler($logger, $appConfig['debug']))->register();

if (!in_array($appConfig['environment'], ['local', 'testing', 'production'], true)) {
    throw new RuntimeException('APP_ENV must be local, testing, or production.');
}

date_default_timezone_set($appConfig['timezone']);

$httpsActive = str_starts_with(strtolower($appConfig['url']), 'https://')
    || (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off');

$session = new Session();
$session->start([
    'name' => $appConfig['session']['name'],
    'cookie_httponly' => true,
    'cookie_secure' => $appConfig['session']['secure'] || $httpsActive,
    'cookie_samesite' => 'Lax',
    'cookie_path' => '/',
    'use_strict_mode' => true,
    'use_only_cookies' => true,
]);
$auth = new Auth($session);
$database = new Database($databaseConfig);
$users = new UserRepository($database);
$profiles = new UserProfileRepository($database);
$externalIdentities = new ExternalIdentityRepository($database);
$userMedia = new UserMediaRepository($database);
$seriesProgress = new UserSeriesProgressRepository($database);
$watchHistory = new WatchHistoryRepository($database);
$userSchedule = new UserScheduleRepository($database);
$personalStatistics = new PersonalStatisticsRepository($database);
$animeSnapshots = new AnimeSnapshotRepository($database);
$mediaPreferences = new UserMediaPreferenceRepository($database);
$publicProfiles = new PublicProfileRepository($database);
$socialNotifications = new SocialNotificationRepository($database);
$socialControls = new UserSocialControlRepository($database);
$directMessages = new DirectMessageRepository($database);
$admins = new AdminUserRepository($database);
$socialReports = new SocialReportRepository($database);
$accountAccess = new AccountAccessRepository($database);
$moderationActions = new ModerationActionRepository($database);
$moderationService = new ModerationActionService($database);
$accountSuspensionGuard = new AccountSuspensionGuard($auth, $session, $accountAccess);
$userFollows = new UserFollowRepository($database, $socialNotifications);
$userFollowRequests = new UserFollowRequestRepository($database, $socialNotifications);
$socialFeed = new SocialFeedRepository($database);
$socialActivityLikes = new SocialActivityLikeRepository($database, $socialNotifications);
$socialActivityComments = new SocialActivityCommentRepository($database, $socialNotifications);
$facebookConfig = $authConfig['facebook'];
$tmdbCache = new FileTmdbPayloadCache($root . '/storage/cache/tmdb', $tmdbConfig['max_response_bytes']);
$tmdb = new TmdbClient(
    readAccessToken: $tmdbConfig['read_access_token'],
    apiBaseUrl: $tmdbConfig['api_base_url'],
    language: $tmdbConfig['language'],
    region: $tmdbConfig['region'],
    connectTimeout: $tmdbConfig['connect_timeout'],
    timeout: $tmdbConfig['timeout'],
    maxResponseBytes: $tmdbConfig['max_response_bytes'],
    cache: $tmdbCache,
);
$contentDiscovery = new ContentDiscoveryService($tmdb, $tmdb);
$request = Request::capture();
$partialNavigation = new PartialNavigationRequest($request);

return [
    'config' => $appConfig,
    'public_url' => new PublicUrl($appConfig['url']),
    'auth_config' => $authConfig,
    'tmdb_config' => $tmdbConfig,
    'request' => $request,
    'router' => new Router(),
    'view' => new View($root . '/resources/views', new CompositeLayoutContextProvider([
        new NotificationLayoutContextProvider($auth, $socialNotifications),
        new MessageLayoutContextProvider($auth, $directMessages),
    ]), $partialNavigation),
    'partial_navigation' => $partialNavigation,
    'session' => $session,
    'auth' => $auth,
    'google_identity_verifier' => new GoogleApiIdentityVerifier($authConfig['google']['client_id']),
    'pending_external_onboarding' => new PendingExternalOnboarding($session),
    'facebook_oauth_state' => new FacebookOAuthState($session),
    'facebook_identity_provider' => new FacebookGraphClient(
        $facebookConfig['app_id'],
        $facebookConfig['app_secret'],
        $facebookConfig['redirect_uri'],
        $facebookConfig['graph_version'],
    ),
    'tmdb' => $tmdb,
    'tmdb_payload_cache' => $tmdbCache,
    'content_discovery' => $contentDiscovery,
    'username_policy' => new UsernamePolicy(),
    'external_identities' => $externalIdentities,
    'profiles' => $profiles,
    'user_media' => $userMedia,
    'series_progress' => $seriesProgress,
    'watch_history' => $watchHistory,
    'user_schedule' => $userSchedule,
    'personal_statistics' => $personalStatistics,
    'anime_snapshots' => $animeSnapshots,
    'media_preferences' => $mediaPreferences,
    'public_profiles' => $publicProfiles,
    'user_follows' => $userFollows,
    'user_follow_requests' => $userFollowRequests,
    'social_feed' => $socialFeed,
    'social_activity_likes' => $socialActivityLikes,
    'social_activity_comments' => $socialActivityComments,
    'social_notifications' => $socialNotifications,
    'direct_messages' => $directMessages,
    'social_controls'=>$socialControls,
    'admins' => $admins,
    'social_reports' => $socialReports,
    'account_access' => $accountAccess,
    'moderation_actions' => $moderationActions,
    'moderation_service' => $moderationService,
    'account_suspension_guard' => $accountSuspensionGuard,
    'private_profile_access_policy' => new PrivateProfileAccessPolicy(),
    'profile_updater' => new ProfileUpdateService($database, $profiles, $userFollowRequests, $profiles),
    'account_creator' => new AccountOnboardingService(
        $database,
        $users,
        $profiles,
        $externalIdentities,
    ),
    'csrf' => new Csrf($session),
    'validator' => new Validator(),
    'database' => $database,
    'logger' => $logger,
];
