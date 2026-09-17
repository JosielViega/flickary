<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\ErrorHandler;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Validation\Validator;
use App\Authentication\AccountOnboardingService;
use App\Authentication\GoogleApiIdentityVerifier;
use App\Authentication\PendingGoogleOnboarding;
use App\Authentication\UsernamePolicy;
use App\Repositories\ExternalIdentityRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
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

return [
    'config' => $appConfig,
    'auth_config' => $authConfig,
    'request' => Request::capture(),
    'router' => new Router(),
    'view' => new View($root . '/resources/views'),
    'session' => $session,
    'auth' => $auth,
    'google_identity_verifier' => new GoogleApiIdentityVerifier($authConfig['google']['client_id']),
    'pending_google_onboarding' => new PendingGoogleOnboarding($session),
    'username_policy' => new UsernamePolicy(),
    'external_identities' => $externalIdentities,
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
