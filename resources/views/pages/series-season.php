<?php

declare(strict_types=1);

/** @var \App\Integrations\Tmdb\TmdbSeasonDetails $details */

foreach ($messages as $type => $items):
    foreach ($items as $message):
        ?>
        <div class="flash flash--<?= $type === 'error' ? 'error' : 'success' ?>" role="status">
            <?= e($message) ?>
        </div>
        <?php
    endforeach;
endforeach;
?>

<article class="season-page">
    <header class="season-hero">
        <div class="season-hero__poster">
            <span aria-hidden="true"><?= e(mb_strtoupper(mb_substr($details->name, 0, 1))) ?></span>
            <?php if ($posterUrl !== null): ?>
                <img
                    src="<?= e($posterUrl) ?>"
                    alt="Pôster de <?= e($details->name) ?>"
                    decoding="async"
                >
            <?php endif; ?>
        </div>

        <div>
            <p class="eyebrow">Série · Temporada <?= e((string) $details->seasonNumber) ?></p>
            <h1><?= e($details->name) ?></h1>
            <?php if ($details->airDate !== null): ?>
                <p><?= e((new DateTimeImmutable($details->airDate))->format('d/m/Y')) ?></p>
            <?php endif; ?>
            <p><?= e($details->overview ?? 'Resumo não disponível.') ?></p>
            <strong><?= count($watched) ?> de <?= count($details->episodes) ?> episódios marcados</strong>
        </div>
    </header>

    <?php if ($auth->check() && !$canTrack): ?>
        <aside class="season-cta">
            Adicione a série à Minha Lista para acompanhar episódios.
            <a href="/series/<?= e((string) $details->seriesSourceId) ?>">Voltar para a série</a>
        </aside>
    <?php elseif (!$auth->check()): ?>
        <aside class="season-cta">
            <a class="button button--primary" href="/login">Entre para acompanhar seus episódios</a>
        </aside>
    <?php else: ?>
        <div class="season-actions">
            <form method="post" action="/series/<?= e((string) $details->seriesSourceId) ?>/temporadas/<?= e((string) $details->seasonNumber) ?>/assistidos">
                <?= $csrf->field() ?>
                <button class="button button--primary" type="submit">Marcar temporada como assistida</button>
            </form>
            <form method="post" action="/series/<?= e((string) $details->seriesSourceId) ?>/temporadas/<?= e((string) $details->seasonNumber) ?>/assistidos/remover">
                <?= $csrf->field() ?>
                <button class="button button--ghost" type="submit">Limpar progresso da temporada</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($details->episodes === []): ?>
        <p class="season-cta">Nenhum episódio disponível para esta temporada.</p>
    <?php else: ?>
        <ol class="episode-list">
            <?php foreach ($details->episodes as $episode): ?>
                <?php $isWatched = in_array($episode->episodeNumber, $watched, true); ?>
                <li class="episode-card<?= $nextEpisode === $episode->episodeNumber ? ' is-next' : '' ?>">
                    <div class="episode-card__number">E<?= e((string) $episode->episodeNumber) ?></div>
                    <div>
                        <h2><?= e($episode->name) ?></h2>
                        <p><?= e($episode->overview ?? 'Resumo não disponível.') ?></p>
                        <small>
                            <?= e($episode->airDate ?? 'Data não informada') ?>
                            <?= $episode->runtime !== null ? ' · ' . e((string) $episode->runtime) . ' min' : '' ?>
                        </small>
                    </div>
                    <div class="episode-card__status">
                        <strong><?= $isWatched ? 'Assistido' : 'Não assistido' ?></strong>
                        <?php if ($nextEpisode === $episode->episodeNumber): ?>
                            <span>Próximo episódio</span>
                        <?php endif; ?>
                        <?php if ($canTrack): ?>
                            <form method="post" action="/series/<?= e((string) $details->seriesSourceId) ?>/temporadas/<?= e((string) $details->seasonNumber) ?>/episodios/<?= e((string) $episode->episodeNumber) ?>/<?= $isWatched ? 'desmarcar' : 'assistido' ?>">
                                <?= $csrf->field() ?>
                                <button class="button button--ghost" type="submit">
                                    <?= $isWatched ? 'Desmarcar' : 'Marcar como assistido' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>

    <footer class="media-detail__footer">
        <a class="media-detail__back" href="/series/<?= e((string) $details->seriesSourceId) ?>">
            ← Voltar para a série
        </a>
        <p class="tmdb-compact-credit">
            Dados e imagens fornecidos por TMDB. <a href="/sobre#tmdb">Saiba mais.</a>
        </p>
    </footer>
</article>
