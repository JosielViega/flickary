<?php

declare(strict_types=1);

use App\History\WatchHistoryDurationBackfill;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

try {
    $result = (new WatchHistoryDurationBackfill($app['watch_history'], $app['tmdb']))->run();
    fwrite(STDOUT, 'Eventos inicialmente sem duração: ' . $result->initiallyMissing . PHP_EOL);
    fwrite(STDOUT, 'Eventos preenchidos: ' . $result->filled . PHP_EOL);
    fwrite(STDOUT, 'Eventos ainda sem duração: ' . $result->remaining . PHP_EOL);
    fwrite(STDOUT, 'Movies consultados: ' . $result->moviesRequested . PHP_EOL);
    fwrite(STDOUT, 'Temporadas consultadas: ' . $result->seasonsRequested . PHP_EOL);
    if ($result->stoppedReason !== null) {
        fwrite(STDERR, 'Backfill interrompido com segurança: ' . $result->stoppedReason . PHP_EOL);
        exit(1);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
