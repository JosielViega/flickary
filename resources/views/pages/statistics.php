<?php

declare(strict_types=1);

/** @var \App\Statistics\PersonalStatistics $statistics */
/** @var array<string,string> $statusOptions */
$durationLabel = \App\History\WatchDuration::format($statistics->knownMinutes);
$maximumMonth = max(1, ...array_map(static fn ($month): int => $month->count, $statistics->monthly));
$unknownDurationLabel = $statistics->unknownDurationEvents === 1
    ? 'visualização ainda não possui duração e não entra neste total.'
    : 'visualizações ainda não possuem duração e não entram neste total.';
?>
<section class="statistics-page" aria-labelledby="statistics-title">
    <nav class="personal-subnav" aria-label="Sua jornada pessoal">
        <a href="/minha-lista">Minha Lista</a>
        <a href="/historico">Histórico</a>
        <a class="is-active" href="/estatisticas" aria-current="page">Estatísticas</a>
    </nav>

    <header class="statistics-hero">
        <p class="eyebrow">Passado · Presente · Futuro</p>
        <h1 id="statistics-title">Sua jornada em números.</h1>
        <p>Um retrato pessoal das histórias que você viveu, acompanha e planeja descobrir.</p>
    </header>

    <?php if (!$statistics->hasAnyData()): ?>
        <div class="statistics-empty">
            <h2>Sua jornada ainda está começando.</h2>
            <p>Explore títulos e registre os momentos que fizerem parte dela.</p>
            <a class="button button--primary" href="/buscar">Explorar títulos</a>
        </div>
    <?php endif; ?>

    <section class="statistics-section" aria-labelledby="statistics-past">
        <header><p class="eyebrow">Passado</p><h2 id="statistics-past">Histórias vividas</h2></header>
        <div class="statistics-metrics">
            <article><strong><?= e((string) $statistics->totalViews) ?></strong><span>Visualizações</span></article>
            <article><strong><?= e((string) $statistics->movieViews) ?></strong><span>Filmes</span></article>
            <article><strong><?= e((string) $statistics->episodeViews) ?></strong><span>Episódios</span></article>
            <article><strong><?= e((string) $statistics->uniqueMovies) ?></strong><span>Filmes únicos</span></article>
            <article><strong><?= e((string) $statistics->uniqueSeries) ?></strong><span>Séries únicas</span></article>
            <article><strong><?= e((string) $statistics->activeDays) ?></strong><span>Dias ativos</span></article>
            <article><strong><?= e((string) $statistics->rewatches) ?></strong><span>Reassistidas</span></article>
        </div>
        <div class="statistics-time">
            <div><p class="eyebrow"><?= $statistics->unknownDurationEvents > 0 ? 'Tempo registrado' : 'Tempo assistido' ?></p><strong><?= e($durationLabel) ?></strong></div>
            <dl><div><dt>Este mês</dt><dd><?= e((string) $statistics->currentMonthViews) ?> visualizações</dd></div><div><dt>Este ano</dt><dd><?= e((string) $statistics->currentYearViews) ?> visualizações</dd></div></dl>
        </div>
        <?php if ($statistics->unknownDurationEvents > 0): ?>
            <p class="statistics-coverage" role="note"><?= e((string) $statistics->unknownDurationEvents) ?> <?= e($unknownDurationLabel) ?></p>
        <?php endif; ?>
        <figure class="statistics-chart" aria-labelledby="monthly-chart-title">
            <figcaption id="monthly-chart-title">Visualizações nos últimos 12 meses</figcaption>
            <div class="statistics-chart__bars">
                <?php foreach ($statistics->monthly as $month): ?>
                    <?php $height = $month->count === 0 ? 0 : max(8, (int) round(($month->count / $maximumMonth) * 100)); ?>
                    <div class="statistics-chart__month" aria-label="<?= e($month->label) ?>: <?= e((string) $month->count) ?> visualizações">
                        <span class="statistics-chart__value"><?= e((string) $month->count) ?></span>
                        <span class="statistics-chart__track" aria-hidden="true"><span style="--bar-height:<?= e((string) $height) ?>%"></span></span>
                        <span class="statistics-chart__label"><?= e($month->label) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </figure>
    </section>

    <section class="statistics-section" aria-labelledby="statistics-present">
        <header><p class="eyebrow">Presente</p><h2 id="statistics-present">Histórias em movimento</h2></header>
        <div class="statistics-split">
            <article class="statistics-panel"><strong class="statistics-panel__total"><?= e((string) $statistics->libraryTotal) ?></strong><h3>Itens na Minha Lista</h3><dl class="statistics-statuses">
                <?php foreach ($statusOptions as $status => $label): ?><div><dt><?= e($label) ?></dt><dd><?= e((string) ($statistics->libraryByStatus[$status] ?? 0)) ?></dd></div><?php endforeach; ?>
            </dl></article>
            <article class="statistics-panel"><strong class="statistics-panel__total"><?= e((string) $statistics->watchedEpisodeProgress) ?></strong><h3>Episódios marcados no progresso atual</h3><p>Este número representa seu acompanhamento atual, não eventos do Histórico.</p></article>
        </div>
    </section>

    <section class="statistics-section" aria-labelledby="statistics-future">
        <header><p class="eyebrow">Futuro</p><h2 id="statistics-future">Planos no horizonte</h2></header>
        <div class="statistics-metrics statistics-metrics--future">
            <article><strong><?= e((string) $statistics->scheduleTotal) ?></strong><span>Total agendado</span></article>
            <article><strong><?= e((string) $statistics->scheduleToday) ?></strong><span>Para hoje</span></article>
            <article><strong><?= e((string) $statistics->scheduleFuture) ?></strong><span>Próximos</span></article>
            <article><strong><?= e((string) $statistics->scheduleOverdue) ?></strong><span>Atrasados</span></article>
        </div>
        <a class="statistics-link" href="/agenda">Ver minha Agenda →</a>
    </section>
</section>
