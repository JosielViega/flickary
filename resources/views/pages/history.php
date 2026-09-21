<?php

declare(strict_types=1);

/** @var \App\History\WatchHistoryPage $pageData */
/** @var bool $hasAnyItems */
/** @var null|string $typeFilter */
/** @var array<int,string> $posterUrls */
/** @var array<string,list<string>> $messages */

$queryFor = static function (int $page) use ($typeFilter): string {
    $query = array_filter(
        ['tipo' => $typeFilter, 'page' => $page],
        static fn ($value): bool => $value !== null,
    );

    return '/historico' . ($query === [] ? '' : '?' . http_build_query($query));
};
$months = [
    1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
    'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
];
$longDate = static function (string $date) use ($months): string {
    $value = new DateTimeImmutable($date);
    return $value->format('j') . ' de ' . $months[(int) $value->format('n')] . ' de ' . $value->format('Y');
};
$currentDate = null;
?>
<section class="history-page" aria-labelledby="history-title">
    <?php foreach ($messages as $type => $items): ?>
        <?php foreach ($items as $message): ?>
            <div class="flash flash--<?= $type === 'error' ? 'error' : 'success' ?>" role="status"><?= e($message) ?></div>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <nav class="personal-subnav" aria-label="Sua coleção e histórico">
        <a href="/minha-lista">Minha Lista</a>
        <a class="is-active" href="/historico" aria-current="page">Histórico</a>
        <a href="/estatisticas">Estatísticas</a>
    </nav>

    <header class="history-hero">
        <p class="eyebrow">Sua linha do tempo</p>
        <h1 id="history-title">Histórico</h1>
        <p>Cada registro é um momento real — inclusive quando uma história merece ser vista outra vez.</p>
    </header>

    <form class="history-filters" method="get" action="/historico">
        <label for="history-type">Tipo</label>
        <select id="history-type" name="tipo">
            <option value="todos">Todos</option>
            <option value="filmes"<?= $typeFilter === 'filmes' ? ' selected' : '' ?>>Filmes</option>
            <option value="episodios"<?= $typeFilter === 'episodios' ? ' selected' : '' ?>>Episódios</option>
        </select>
        <button class="button button--primary" type="submit">Aplicar filtro</button>
    </form>

    <?php if (!$hasAnyItems): ?>
        <div class="library-empty">
            <h2>Seu histórico ainda está vazio.</h2>
            <p>Registre um filme ou episódio quando ele fizer parte da sua jornada.</p>
            <a class="button button--primary" href="/buscar">Explorar títulos</a>
        </div>
    <?php elseif ($pageData->items === []): ?>
        <div class="library-empty">
            <h2>Nenhum registro corresponde a este filtro.</h2>
            <a class="button button--ghost" href="/historico">Limpar filtro</a>
        </div>
    <?php else: ?>
        <div class="history-timeline">
            <?php foreach ($pageData->items as $item): ?>
                <?php if ($currentDate !== $item->watchedOn): ?>
                    <?php $currentDate = $item->watchedOn; ?>
                    <h2 class="history-date"><?= e($longDate($item->watchedOn)) ?></h2>
                <?php endif; ?>
                <?php
                $isMovie = $item->entryType === 'movie';
                $path = $isMovie
                    ? '/filmes/' . $item->sourceId
                    : '/series/' . $item->sourceId . '/temporadas/' . $item->seasonNumber;
                ?>
                <article class="history-card">
                    <a class="history-card__poster" href="<?= e($path) ?>" aria-label="Ver <?= e($item->title) ?>">
                        <span aria-hidden="true"><?= e(mb_strtoupper(mb_substr($item->title, 0, 1))) ?></span>
                        <?php if (isset($posterUrls[$item->id])): ?>
                            <img src="<?= e($posterUrls[$item->id]) ?>" alt="Pôster de <?= e($item->title) ?>" loading="lazy" decoding="async" width="500" height="750">
                        <?php endif; ?>
                    </a>
                    <div class="history-card__body">
                        <p class="history-card__kind"><?= $isMovie ? 'Filme' : 'Série' ?></p>
                        <h3><a href="<?= e($path) ?>"><?= e($item->title) ?></a></h3>
                        <?php if ($isMovie): ?>
                            <?php if ($item->year() !== null): ?><p><?= e((string) $item->year()) ?></p><?php endif; ?>
                        <?php else: ?>
                            <p class="history-card__episode">T<?= e((string) $item->seasonNumber) ?>E<?= e((string) $item->episodeNumber) ?> · <?= e((string) $item->episodeTitle) ?></p>
                        <?php endif; ?>
                        <p>Assistido em <?= e((new DateTimeImmutable($item->watchedOn))->format('d/m/Y')) ?></p>
                        <?php if ($item->durationMinutes !== null): ?><p><?= e(\App\History\WatchDuration::format($item->durationMinutes)) ?></p><?php endif; ?>
                    </div>
                    <div class="history-card__actions">
                        <form method="post" action="/historico/<?= e((string) $item->id) ?>">
                            <?= $csrf->field() ?>
                            <label for="history-date-<?= e((string) $item->id) ?>">Corrigir data</label>
                            <input id="history-date-<?= e((string) $item->id) ?>" type="date" name="watched_on" value="<?= e($item->watchedOn) ?>" max="<?= e($today) ?>" required>
                            <button class="button button--ghost" type="submit">Salvar data</button>
                        </form>
                        <form method="post" action="/historico/<?= e((string) $item->id) ?>/remover">
                            <?= $csrf->field() ?>
                            <button class="button button--danger" type="submit">Remover registro</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($pageData->lastPage() > 1): ?>
            <nav class="pagination" aria-label="Paginação do histórico">
                <?php if ($pageData->page > 1): ?><a href="<?= e($queryFor($pageData->page - 1)) ?>">← Anterior</a><?php endif; ?>
                <span>Página <?= e((string) $pageData->page) ?> de <?= e((string) $pageData->lastPage()) ?></span>
                <?php if ($pageData->page < $pageData->lastPage()): ?><a href="<?= e($queryFor($pageData->page + 1)) ?>">Próxima →</a><?php endif; ?>
            </nav>
        <?php endif; ?>
        <p class="tmdb-compact-credit">Dados e imagens fornecidos por TMDB. <a href="/sobre#tmdb">Saiba mais sobre os créditos.</a></p>
    <?php endif; ?>
</section>
