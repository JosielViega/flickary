<?php

declare(strict_types=1);

use App\Authentication\UsernamePolicy;
use App\Core\Database;
use App\Repositories\AdminUserRepository;
use Dotenv\Dotenv;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
Dotenv::createImmutable($root)->safeLoad();

$usage = static function (): never {
    fwrite(STDERR, "Uso:\n  php bin/admin.php grant <username>\n  php bin/admin.php revoke <username>\n  php bin/admin.php list\n");
    exit(2);
};

$operation = $argv[1] ?? null;
if (!is_string($operation) || !in_array($operation, ['grant', 'revoke', 'list'], true)) {
    $usage();
}
if ($operation === 'list' && count($argv) !== 2) { $usage(); }
if ($operation !== 'list' && count($argv) !== 3) { $usage(); }

try {
    $repository = new AdminUserRepository(new Database(require $root . '/config/database.php'));
    if ($operation === 'list') {
        $usernames = $repository->adminUsernames();
        if ($usernames === []) {
            fwrite(STDOUT, "Nenhum administrador configurado.\n");
            exit(0);
        }
        foreach ($usernames as $username) { fwrite(STDOUT, $username . PHP_EOL); }
        exit(0);
    }

    $policy = new UsernamePolicy();
    $username = $policy->normalize($argv[2] ?? null);
    $error = $policy->error($username);
    if ($username === null || $username !== ($argv[2] ?? null) || $error !== null) {
        fwrite(STDERR, ($error ?? 'Username inválido.') . PHP_EOL);
        exit(2);
    }
    if (!$repository->setAdmin($username, $operation === 'grant')) {
        fwrite(STDERR, 'Usuário não encontrado: @' . $username . PHP_EOL);
        exit(1);
    }
    fwrite(STDOUT, $operation === 'grant'
        ? 'Acesso administrativo concedido a @' . $username . PHP_EOL
        : 'Acesso administrativo revogado de @' . $username . PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Não foi possível atualizar o acesso administrativo.' . PHP_EOL);
    exit(1);
}
