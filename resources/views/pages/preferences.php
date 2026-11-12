<?php

declare(strict_types=1);

/** @var string $mode */
/** @var \App\Preferences\UserMediaPreferencePage $pageData */
/** @var bool $hasAnyItems */
/** @var string $typeFilter */
/** @var array<int,string> $posterUrls */
/** @var array<string,list<string>> $messages */
$favorites = $mode === 'favorites';
$basePath = $favorites ? '/favoritos' : '/avaliacoes';
$personalSection = $favorites ? 'favorites' : 'ratings';
$queryFor = static function (int $page) use ($basePath, $typeFilter): string {
    $query = ['tipo'=>$typeFilter,'page'=>$page];
    return $basePath . ($query === [] ? '' : '?' . http_build_query($query));
};
?>
<section class="library-page preference-page" aria-labelledby="preference-title">
    <?php foreach ($messages as $type => $items): foreach ($items as $message): ?>
        <div class="flash flash--<?= $type === 'error' ? 'error' : 'success' ?>" role="status"><?= e($message) ?></div>
    <?php endforeach; endforeach; ?>

    <?php require dirname(__DIR__) . '/components/personal-subnav.php'; ?>

    <header class="library-hero">
        <p class="eyebrow">Sua opinião, no seu tempo</p>
        <h1 id="preference-title"><?= $favorites ? 'Favoritos' : 'Avaliações' ?></h1>
        <p><?= $favorites ? 'As histórias que ganharam um lugar especial para você.' : 'Suas notas pessoais para filmes e séries, independentes da nota TMDB.' ?></p>
    </header>

    <form class="library-filters preference-filters" method="get" action="<?= e($basePath) ?>">
        <div><label for="preference-type">Tipo</label><select id="preference-type" name="tipo">
            <option value="todos"<?= $typeFilter === 'todos' ? ' selected' : '' ?>>Todos</option>
            <option value="filmes"<?= $typeFilter === 'filmes' ? ' selected' : '' ?>>Filmes</option>
            <option value="series"<?= $typeFilter === 'series' ? ' selected' : '' ?>>Séries</option>
            <option value="anime"<?= $typeFilter === 'anime' ? ' selected' : '' ?>>Anime</option>
        </select></div>
        <button class="button button--primary" type="submit">Aplicar filtro</button>
    </form>

    <?php if (!$hasAnyItems): ?>
        <div class="library-empty"><h2><?= $favorites ? 'Você ainda não marcou nenhum favorito.' : 'Você ainda não avaliou nenhum título.' ?></h2><p>Explore o catálogo e registre sua opinião quando uma história encontrar você.</p><a class="button button--primary" href="/buscar">Explorar títulos</a></div>
    <?php elseif ($pageData->items === []): ?>
        <div class="library-empty"><h2>Nenhum item corresponde a este filtro.</h2><a class="button button--ghost" href="<?= e($basePath) ?>">Limpar filtro</a></div>
    <?php else: ?>
        <div class="library-grid">
            <?php foreach ($pageData->items as $item): ?>
                <?php $path = ($item->mediaType === 'movie' ? '/filmes/' : '/series/') . $item->sourceId; ?>
                <article class="library-card preference-card"><a href="<?= e($path) ?>" aria-label="Ver detalhes de <?= e($item->title) ?>">
                    <div class="library-card__poster"><span aria-hidden="true"><?= e(mb_strtoupper(mb_substr($item->title, 0, 1))) ?></span><?php if (isset($posterUrls[$item->id])): ?><img src="<?= e($posterUrls[$item->id]) ?>" alt="Pôster de <?= e($item->title) ?>" loading="lazy" decoding="async" width="500" height="750"><?php endif; ?></div>
                    <div class="library-card__body"><p><?= $item->isAnime === true ? 'Anime · ' : '' ?><?= $item->mediaType === 'movie' ? 'Filme' : 'Série' ?><?= $item->year() !== null ? ' · ' . e((string)$item->year()) : '' ?></p><h2><?= e($item->title) ?></h2><?php if ($item->rating !== null): ?><span>Sua nota <?= e((string)$item->rating) ?>/10</span><?php endif; ?><?php if (!$favorites && $item->isFavorite): ?><span>Favorito</span><?php endif; ?></div>
                </a></article>
            <?php endforeach; ?>
        </div>
        <?php if ($pageData->lastPage() > 1): ?><nav class="pagination" aria-label="Paginação"><?php if ($pageData->page > 1): ?><a href="<?= e($queryFor($pageData->page - 1)) ?>">← Anterior</a><?php endif; ?><span>Página <?= e((string)$pageData->page) ?> de <?= e((string)$pageData->lastPage()) ?></span><?php if ($pageData->page < $pageData->lastPage()): ?><a href="<?= e($queryFor($pageData->page + 1)) ?>">Próxima →</a><?php endif; ?></nav><?php endif; ?>
        <p class="tmdb-compact-credit">Dados e imagens fornecidos por TMDB. <a href="/sobre#tmdb">Saiba mais sobre os créditos.</a></p>
    <?php endif; ?>
</section>
