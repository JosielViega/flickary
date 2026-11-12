<?php

declare(strict_types=1);

use App\Anime\AnimeSnapshotBackfill;

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$result = (new AnimeSnapshotBackfill($app['anime_snapshots'], $app['tmdb']))->run();

fwrite(STDOUT, 'Snapshots inicialmente pendentes: ' . $result->initiallyPending . PHP_EOL);
fwrite(STDOUT, 'Identidades Movie consultadas: ' . $result->moviesRequested . PHP_EOL);
fwrite(STDOUT, 'Identidades Series consultadas: ' . $result->seriesRequested . PHP_EOL);
fwrite(STDOUT, 'Snapshots classificados Anime: ' . $result->classifiedAnime . PHP_EOL);
fwrite(STDOUT, 'Snapshots classificados não Anime: ' . $result->classifiedNonAnime . PHP_EOL);
fwrite(STDOUT, 'Snapshots ainda pendentes: ' . $result->remaining . PHP_EOL);
if ($result->stoppedReason !== null) {
    fwrite(STDERR, 'Backfill interrompido com segurança: ' . $result->stoppedReason . PHP_EOL);
    exit(1);
}
