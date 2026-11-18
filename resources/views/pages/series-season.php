<?php

declare(strict_types=1);

/** @var \App\Integrations\Tmdb\TmdbSeasonDetails $details */
/** @var array<int,string> $episodeStillUrls */

foreach ($messages as $type => $items):
    foreach ($items as $message): ?>
        <div class="flash flash--<?= $type === 'error' ? 'error' : 'success' ?>" role="status"><?= e($message) ?></div>
    <?php endforeach;
endforeach;

$episodeCount = count($details->episodes);
$watchedCount = count($watched);
$progress = $episodeCount > 0 ? (int) round($watchedCount / $episodeCount * 100) : 0;
$seasonPath = '/series/' . $details->seriesSourceId . '/temporadas/' . $details->seasonNumber;
?>
<article class="season-page">
    <header class="season-hero">
        <div class="season-hero__poster">
            <span aria-hidden="true"><?= e(mb_strtoupper(mb_substr($details->name, 0, 1))) ?></span>
            <?php if ($posterUrl !== null): ?><img src="<?= e($posterUrl) ?>" alt="Pôster de <?= e($details->name) ?>" decoding="async" width="500" height="750"><?php endif; ?>
        </div>
        <div class="season-hero__body">
            <p class="eyebrow">Série · Temporada <?= e((string) $details->seasonNumber) ?></p>
            <h1><?= e($details->name) ?></h1>
            <p class="season-hero__meta"><?php if ($details->airDate !== null): ?><span><?= e((new DateTimeImmutable($details->airDate))->format('Y')) ?></span><?php endif; ?><span><?= $episodeCount ?> episódios</span></p>
            <p class="season-hero__overview"><?= e($details->overview ?? 'Resumo não disponível.') ?></p>
            <div class="season-progress" aria-label="<?= $watchedCount ?> de <?= $episodeCount ?> episódios assistidos"><span><i style="--progress:<?= $progress ?>%"></i></span><strong><?= $watchedCount ?>/<?= $episodeCount ?> assistidos</strong></div>
        </div>
    </header>

    <section class="season-toolbar" aria-label="Ações da temporada">
        <?php if (!$auth->check()): ?>
            <a class="season-toolbar__login" href="/login">Entre para acompanhar seus episódios</a>
        <?php elseif (!$canTrack): ?>
            <p>Adicione a série à Minha Lista para acompanhar episódios.</p><a href="/series/<?= e((string) $details->seriesSourceId) ?>">Ir para a série</a>
        <?php else: ?>
            <form method="post" action="<?= e($seasonPath . '/assistidos') ?>"><?= $csrf->field() ?><button class="button button--primary" type="submit">Marcar temporada assistida</button></form>
            <form method="post" action="<?= e($seasonPath . '/assistidos/remover') ?>"><?= $csrf->field() ?><button class="button button--ghost" type="submit">Limpar progresso</button></form>
        <?php endif; ?>
    </section>

    <?php if ($details->episodes === []): ?>
        <p class="season-cta">Nenhum episódio disponível para esta temporada.</p>
    <?php else: ?>
        <ol class="episode-list">
            <?php foreach ($details->episodes as $episode):
                $isWatched = in_array($episode->episodeNumber, $watched, true);
                $episodeSchedule = $episodeSchedules[$episode->episodeNumber] ?? null;
                $episodePath = $seasonPath . '/episodios/' . $episode->episodeNumber;
            ?>
                <li class="episode-card<?= $nextEpisode === $episode->episodeNumber ? ' is-next' : '' ?><?= $isWatched ? ' is-watched' : '' ?>">
                    <div class="episode-card__still">
                        <span aria-hidden="true">E<?= e((string) $episode->episodeNumber) ?></span>
                        <?php if (isset($episodeStillUrls[$episode->episodeNumber])): ?><img src="<?= e($episodeStillUrls[$episode->episodeNumber]) ?>" alt="Cena do episódio <?= e((string) $episode->episodeNumber) ?>" loading="lazy" decoding="async" width="500" height="281"><?php endif; ?>
                        <?php if ($isWatched): ?><strong>✓ Assistido</strong><?php elseif ($nextEpisode === $episode->episodeNumber): ?><strong>▶ Próximo</strong><?php endif; ?>
                    </div>
                    <div class="episode-card__body">
                        <p class="episode-card__eyebrow">Episódio <?= e((string) $episode->episodeNumber) ?></p>
                        <h2><?= e($episode->name) ?></h2>
                        <p class="episode-card__overview"><?= e($episode->overview ?? 'Resumo não disponível.') ?></p>
                        <p class="episode-card__meta"><?php if ($episode->airDate !== null): ?><span><?= e((new DateTimeImmutable($episode->airDate))->format('d/m/Y')) ?></span><?php endif; ?><?php if ($episode->runtime !== null): ?><span><?= e((string) $episode->runtime) ?> min</span><?php endif; ?></p>
                    </div>
                    <?php if ($auth->check()): ?>
                        <details class="episode-actions">
                            <summary>Ações <span aria-hidden="true">＋</span></summary>
                            <div class="episode-actions__panel">
                                <?php if ($canTrack): ?><form method="post" action="<?= e($episodePath . '/' . ($isWatched ? 'desmarcar' : 'assistido')) ?>"><?= $csrf->field() ?><button class="button button--ghost" type="submit"><?= $isWatched ? 'Desmarcar assistido' : 'Marcar como assistido' ?></button></form><?php endif; ?>
                                <form class="episode-history-form" method="post" action="<?= e($episodePath . '/historico') ?>"><?= $csrf->field() ?><input type="hidden" name="request_key" value="<?= e($episodeHistoryKeys[$episode->episodeNumber]) ?>"><label for="history-episode-<?= e((string) $episode->episodeNumber) ?>">Histórico</label><input id="history-episode-<?= e((string) $episode->episodeNumber) ?>" type="date" name="watched_on" value="<?= e($today) ?>" max="<?= e($today) ?>" required><button class="button button--ghost" type="submit">Registrar visualização</button></form>
                                <form class="episode-schedule-form" method="post" action="<?= e($episodePath . '/agenda') ?>"><?= $csrf->field() ?><label for="schedule-episode-<?= e((string) $episode->episodeNumber) ?>">Agenda<?= $episodeSchedule === null ? '' : ' · ' . e((new DateTimeImmutable($episodeSchedule->scheduledOn))->format('d/m/Y')) ?></label><input id="schedule-episode-<?= e((string) $episode->episodeNumber) ?>" type="date" name="scheduled_on" value="<?= e($episodeSchedule?->scheduledOn ?? $today) ?>" min="<?= e($today) ?>" required><button class="button button--ghost" type="submit"><?= $episodeSchedule === null ? 'Agendar episódio' : 'Reagendar' ?></button></form>
                                <?php if ($episodeSchedule !== null): ?><form method="post" action="/agenda/<?= e((string) $episodeSchedule->id) ?>/remover"><?= $csrf->field() ?><button class="button button--ghost" type="submit">Remover da Agenda</button></form><?php endif; ?>
                            </div>
                        </details>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>

    <footer class="media-detail__footer"><a class="media-detail__back" href="/series/<?= e((string) $details->seriesSourceId) ?>">← Voltar para a série</a><p class="tmdb-compact-credit">Dados e imagens fornecidos por TMDB. <a href="/sobre#tmdb">Saiba mais.</a></p></footer>
</article>
