<?php

declare(strict_types=1);

/** @var \App\Media\UserMediaPage $pageData */
/** @var bool $hasAnyItems */
/** @var null|string $statusFilter */
/** @var null|string $typeFilter */
/** @var array<string,string> $statusOptions */
/** @var array<int,string> $posterUrls */
/** @var array<string,list<string>> $messages */

$queryFor = static function (int $page) use ($statusFilter, $typeFilter): string {
    $query = array_filter(['status' => $statusFilter, 'tipo' => $typeFilter, 'page' => $page], static fn ($value): bool => $value !== null);
    return '/minha-lista?' . http_build_query($query);
};
?>
<section class="library-page" aria-labelledby="library-title">
    <?php foreach ($messages as $type => $items): ?>
        <?php foreach ($items as $message): ?>
            <div class="flash flash--<?= $type === 'error' ? 'error' : 'success' ?>" role="status"><?= e($message) ?></div>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <header class="library-hero">
        <p class="eyebrow">Seu tempo, suas histórias</p>
        <h1 id="library-title">Minha Lista</h1>
        <p>Acompanhe o estado atual dos filmes e séries que fazem parte da sua jornada.</p>
    </header>

    <form class="library-filters" method="get" action="/minha-lista">
        <div><label for="library-status">Status</label><select id="library-status" name="status"><option value="">Todos</option><?php foreach ($statusOptions as $value => $label): ?><option value="<?= e($value) ?>"<?= $statusFilter === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div><label for="library-type">Tipo</label><select id="library-type" name="tipo"><option value="">Todos</option><option value="filmes"<?= $typeFilter === 'filmes' ? ' selected' : '' ?>>Filmes</option><option value="series"<?= $typeFilter === 'series' ? ' selected' : '' ?>>Séries</option></select></div>
        <button class="button button--primary" type="submit">Aplicar filtros</button>
    </form>

    <?php if (!$hasAnyItems): ?>
        <div class="library-empty"><h2>Sua lista ainda está vazia.</h2><p>Encontre uma história e escolha como ela faz parte do seu momento.</p><a class="button button--primary" href="/buscar">Explorar títulos</a></div>
    <?php elseif ($pageData->items === []): ?>
        <div class="library-empty"><h2>Nenhum item corresponde a estes filtros.</h2><a class="button button--ghost" href="/minha-lista">Limpar filtros</a></div>
    <?php else: ?>
        <div class="library-grid">
            <?php foreach ($pageData->items as $item): ?>
                <?php $path = ($item->mediaType === 'movie' ? '/filmes/' : '/series/') . $item->sourceId; ?>
                <article class="library-card"><a href="<?= e($path) ?>" aria-label="Ver detalhes de <?= e($item->title) ?>">
                    <div class="library-card__poster"><span aria-hidden="true"><?= e(mb_strtoupper(mb_substr($item->title, 0, 1))) ?></span><?php if (isset($posterUrls[$item->id])): ?><img src="<?= e($posterUrls[$item->id]) ?>" alt="Pôster de <?= e($item->title) ?>" loading="lazy" decoding="async" width="500" height="750"><?php endif; ?></div>
                    <div class="library-card__body"><p><?= $item->mediaType === 'movie' ? 'Filme' : 'Série' ?><?= $item->year() !== null ? ' · ' . e((string) $item->year()) : '' ?></p><h2><?= e($item->title) ?></h2><span><?= e(\App\Media\UserMediaStatus::label($item->status)) ?></span></div>
                </a></article>
            <?php endforeach; ?>
        </div>
        <?php if ($pageData->lastPage() > 1): ?><nav class="pagination" aria-label="Paginação"><?php if ($pageData->page > 1): ?><a href="<?= e($queryFor($pageData->page - 1)) ?>">← Anterior</a><?php endif; ?><span>Página <?= e((string) $pageData->page) ?> de <?= e((string) $pageData->lastPage()) ?></span><?php if ($pageData->page < $pageData->lastPage()): ?><a href="<?= e($queryFor($pageData->page + 1)) ?>">Próxima →</a><?php endif; ?></nav><?php endif; ?>
        <p class="tmdb-compact-credit">Dados e imagens fornecidos por TMDB. <a href="/sobre#tmdb">Saiba mais sobre os créditos.</a></p>
    <?php endif; ?>
</section>
