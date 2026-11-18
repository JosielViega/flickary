<?php

declare(strict_types=1);

/** @var null|\App\Integrations\Tmdb\TmdbMediaDetails $details */
/** @var array<int,string> $seasonPosterUrls */
/** @var list<\App\Integrations\Tmdb\TmdbMedia> $companionItems */

foreach ($messages ?? [] as $type => $items):
    foreach ($items as $message): ?>
        <div class="flash flash--<?= $type === 'error' ? 'error' : 'success' ?>" role="status"><?= e($message) ?></div>
    <?php endforeach;
endforeach;

if ($details === null): ?>
    <section class="media-detail-state" aria-labelledby="media-detail-state-title">
        <p class="eyebrow">Catálogo Flickary</p>
        <div class="media-detail-state__code" aria-hidden="true"><?= $errorTitle === 'Título não encontrado' ? '404' : '—' ?></div>
        <h1 id="media-detail-state-title"><?= e($errorTitle ?? 'Detalhes indisponíveis') ?></h1>
        <p><?= e($errorMessage ?? 'Não foi possível carregar este título.') ?></p>
        <a class="media-detail__back" href="/buscar">← Voltar para buscar</a>
    </section>
<?php else:
    $authenticated = isset($auth) && $auth instanceof \App\Core\Auth && $auth->check();
    $basePath = $details->mediaType === 'movie' ? '/filmes/' : '/series/';
    $mediaPath = $basePath . $details->sourceId;
    $typeLabel = $details->mediaType === 'movie' ? 'Filme' : 'Série';
    $initial = mb_strtoupper(mb_substr($details->title, 0, 1));
    $releaseYear = $details->year ?? ($details->releaseDate === null ? null : (int) substr($details->releaseDate, 0, 4));
    $runtimeLabel = $details->runtime === null ? null : sprintf(
        '%s%s',
        $details->runtime >= 60 ? intdiv($details->runtime, 60) . 'h' : '',
        $details->runtime % 60 > 0 ? ($details->runtime % 60) . 'min' : '',
    );
    $sortedSeasons = $details->seasons;
    usort($sortedSeasons, static fn ($left, $right): int => ($left->seasonNumber === 0 ? 1 : 0) <=> ($right->seasonNumber === 0 ? 1 : 0) ?: $left->seasonNumber <=> $right->seasonNumber);
?>
<article class="media-detail">
    <section class="media-detail__hero" aria-labelledby="media-detail-title">
        <div class="media-detail__backdrop" aria-hidden="true">
            <?php if ($backdropUrl !== null): ?><img src="<?= e($backdropUrl) ?>" alt="" decoding="async" width="1280" height="720"><?php endif; ?>
        </div>
        <div class="media-detail__hero-content">
            <div class="media-detail__poster">
                <span aria-hidden="true"><?= e($initial) ?></span>
                <?php if ($posterUrl !== null): ?><img src="<?= e($posterUrl) ?>" alt="Pôster de <?= e($details->title) ?>" decoding="async" width="500" height="750"><?php endif; ?>
            </div>
            <div class="media-detail__identity">
                <p class="eyebrow"><?= $isAnime ? 'Anime · ' : '' ?><?= e($typeLabel) ?> · TMDB</p>
                <h1 id="media-detail-title"><?= e($details->title) ?></h1>
                <div class="media-detail__metadata" aria-label="Informações principais">
                    <?php if ($releaseYear !== null): ?><span><?= e((string) $releaseYear) ?></span><?php endif; ?>
                    <?php if ($runtimeLabel !== null): ?><span><?= e($runtimeLabel) ?></span><?php endif; ?>
                    <?php if ($details->numberOfSeasons !== null): ?><span><?= e((string) $details->numberOfSeasons) ?> temporadas</span><?php endif; ?>
                    <?php if ($details->voteAverage !== null): ?><span>TMDB <?= e(number_format($details->voteAverage, 1, ',', '')) ?></span><?php endif; ?>
                </div>
                <?php if ($details->genres !== []): ?>
                    <ul class="media-detail__genres" aria-label="Gêneros"><?php foreach ($details->genres as $genre): ?><li><?= e($genre['name']) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <?php if ($details->tagline !== null): ?><p class="media-detail__tagline">“<?= e($details->tagline) ?>”</p><?php endif; ?>
                <?php if ($details->originalTitle !== null && $details->originalTitle !== $details->title): ?><p class="media-detail__original">Título original: <?= e($details->originalTitle) ?></p><?php endif; ?>
                <?php if ($details->voteCount !== null && $details->voteAverage !== null): ?><p class="media-detail__vote-note"><?= e(number_format($details->voteCount, 0, ',', '.')) ?> avaliações no TMDB</p><?php endif; ?>
            </div>
        </div>
    </section>

    <nav class="media-action-rail" aria-label="Ações do título">
        <?php if (!$authenticated): ?>
            <a href="/login"><span aria-hidden="true">＋</span><strong>Minha Lista</strong><small>Entrar</small></a>
            <a href="/login"><span aria-hidden="true">♡</span><strong>Favorito</strong><small>Entrar</small></a>
            <a href="/login"><span aria-hidden="true">◇</span><strong>Agenda</strong><small>Entrar</small></a>
            <?php if ($details->mediaType === 'movie'): ?><a href="/login"><span aria-hidden="true">✓</span><strong>Assistido</strong><small>Entrar</small></a><?php endif; ?>
        <?php else: ?>
            <details class="media-action">
                <summary><span aria-hidden="true">＋</span><strong>Minha Lista</strong><small><?= $userMedia === null ? 'Adicionar' : e(\App\Media\UserMediaStatus::label($userMedia->status)) ?></small></summary>
                <div class="media-action__panel">
                    <form method="post" action="<?= e($mediaPath . '/lista') ?>"><?= $csrf->field() ?><label for="media-status">Estado atual</label><select id="media-status" name="status"><?php foreach ($statusOptions as $value => $label): ?><option value="<?= e($value) ?>"<?= ($userMedia?->status ?? 'planned') === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><button class="button button--primary" type="submit"><?= $userMedia === null ? 'Adicionar à Minha Lista' : 'Salvar status' ?></button></form>
                    <?php if ($userMedia !== null): ?><form method="post" action="<?= e($mediaPath . '/lista/remover') ?>"><?= $csrf->field() ?><button class="button button--ghost" type="submit">Remover da Minha Lista</button></form><?php endif; ?>
                </div>
            </details>
            <details class="media-action">
                <summary><span aria-hidden="true"><?= $userPreference?->isFavorite ? '♥' : '♡' ?></span><strong>Favorito</strong><small><?= $userPreference?->rating !== null ? e((string) $userPreference->rating) . '/10' : ($userPreference?->isFavorite ? 'Salvo' : 'Avaliar') ?></small></summary>
                <div class="media-action__panel"><form method="post" action="<?= e($mediaPath . '/preferencias') ?>"><?= $csrf->field() ?><label class="preference-control__favorite"><input type="checkbox" name="favorite" value="1"<?= $userPreference?->isFavorite ? ' checked' : '' ?>> Marcar como favorito</label><label for="personal-rating">Sua nota</label><select id="personal-rating" name="rating"><option value="">Sem nota</option><?php for ($rating = 1; $rating <= 10; $rating++): ?><option value="<?= $rating ?>"<?= $userPreference?->rating === $rating ? ' selected' : '' ?>><?= $rating ?>/10</option><?php endfor; ?></select><button class="button button--primary" type="submit">Salvar preferências</button></form></div>
            </details>
            <details class="media-action">
                <summary><span aria-hidden="true">◇</span><strong>Agenda</strong><small><?= $scheduleItem === null ? 'Agendar' : e((new DateTimeImmutable($scheduleItem->scheduledOn))->format('d/m')) ?></small></summary>
                <div class="media-action__panel"><form method="post" action="<?= e($mediaPath . '/agenda') ?>"><?= $csrf->field() ?><label for="media-scheduled-on"><?= $scheduleItem === null ? 'Quando?' : 'Reagendar para' ?></label><input id="media-scheduled-on" type="date" name="scheduled_on" value="<?= e($scheduleItem?->scheduledOn ?? $today) ?>" min="<?= e($today) ?>" required><button class="button button--primary" type="submit"><?= $scheduleItem === null ? 'Adicionar à Agenda' : 'Reagendar' ?></button></form><?php if ($scheduleItem !== null): ?><form method="post" action="/agenda/<?= e((string) $scheduleItem->id) ?>/remover"><?= $csrf->field() ?><button class="button button--ghost" type="submit">Remover da Agenda</button></form><?php endif; ?></div>
            </details>
            <?php if ($details->mediaType === 'movie' && $historyRequestKey !== null): ?>
                <details class="media-action"><summary><span aria-hidden="true">✓</span><strong>Assistido</strong><small>Registrar</small></summary><div class="media-action__panel"><form method="post" action="<?= e($mediaPath . '/historico') ?>"><?= $csrf->field() ?><input type="hidden" name="request_key" value="<?= e($historyRequestKey) ?>"><label for="movie-watched-on">Quando você assistiu?</label><input id="movie-watched-on" type="date" name="watched_on" value="<?= e($today) ?>" max="<?= e($today) ?>" required><button class="button button--primary" type="submit">Registrar visualização</button></form></div></details>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (is_array($shareData ?? null)): ?>
            <details class="media-action media-action--share"><summary><span aria-hidden="true">↗</span><strong>Compartilhar</strong><small>Enviar</small></summary><div class="media-action__panel"><?php require dirname(__DIR__) . '/components/share-actions.php'; ?></div></details>
        <?php endif; ?>
    </nav>

    <section class="media-detail__overview" aria-labelledby="media-detail-overview-title">
        <p class="eyebrow">Sobre esta história</p><h2 id="media-detail-overview-title">Sinopse</h2>
        <details class="media-overview" open><summary>Mostrar ou ocultar sinopse</summary><p><?= e($details->overview ?? 'Sinopse não disponível.') ?></p></details>
    </section>

    <?php if ($details->mediaType === 'series' && $sortedSeasons !== []):
        $mainTotal = 0; $mainWatched = 0;
        foreach ($sortedSeasons as $season) if ($season->seasonNumber > 0) { $mainTotal += $season->episodeCount; $mainWatched += min($season->episodeCount, $progressCounts[$season->seasonNumber] ?? 0); }
    ?>
        <section class="season-list" aria-labelledby="season-list-title">
            <header><div><p class="eyebrow">Episódios</p><h2 id="season-list-title">Temporadas</h2></div><?php if ($authenticated): ?><p><?= e((string) $mainWatched) ?> de <?= e((string) $mainTotal) ?> episódios principais</p><?php endif; ?></header>
            <div class="season-list__grid">
                <?php foreach ($sortedSeasons as $season): $watchedCount = min($season->episodeCount, $progressCounts[$season->seasonNumber] ?? 0); $progress = $season->episodeCount > 0 ? (int) round($watchedCount / $season->episodeCount * 100) : 0; ?>
                    <a class="season-card" href="/series/<?= e((string) $details->sourceId) ?>/temporadas/<?= e((string) $season->seasonNumber) ?>">
                        <span class="season-card__poster"><span aria-hidden="true"><?= e(mb_strtoupper(mb_substr($season->name, 0, 1))) ?></span><?php if (isset($seasonPosterUrls[$season->seasonNumber])): ?><img src="<?= e($seasonPosterUrls[$season->seasonNumber]) ?>" alt="Pôster de <?= e($season->name) ?>" loading="lazy" decoding="async" width="342" height="513"><?php endif; ?></span>
                        <span class="season-card__body"><strong><?= e($season->name) ?></strong><small><?= e((string) $season->episodeCount) ?> episódios</small><?php if ($authenticated): ?><span class="season-card__progress"><i style="--progress:<?= $progress ?>%"></i></span><small><?= e((string) $watchedCount) ?> assistidos</small><?php endif; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (($companionItems ?? []) !== []): ?>
        <section class="media-companions" aria-labelledby="media-companions-title"><header><p class="eyebrow">Continue explorando</p><h2 id="media-companions-title">Descubra também</h2></header><div class="media-companions__track"><?php foreach ($companionItems as $media): $posterUrl = $companionPosterUrls[$media->mediaType . ':' . $media->sourceId] ?? null; require dirname(__DIR__) . '/components/discovery-card.php'; endforeach; ?></div></section>
    <?php endif; ?>

    <footer class="media-detail__footer"><a class="media-detail__back" href="/buscar">← Voltar para buscar</a><p class="tmdb-compact-credit">Dados e imagens fornecidos por TMDB. <a href="/sobre#tmdb">Saiba mais sobre os créditos.</a></p></footer>
</article>
<?php endif; ?>
