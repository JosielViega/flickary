<?php

declare(strict_types=1);

/** @var string $type */
/** @var list<array> $sections */
/** @var null|array $pagination */
$filterUrl = static fn (string $filter): string => '/anime?' . http_build_query(['tipo' => $filter], '', '&', PHP_QUERY_RFC3986);
?>
<section class="anime-page" aria-labelledby="anime-title">
    <header class="anime-hero">
        <div>
            <p class="eyebrow">Animação japonesa no TMDB</p>
            <h1 id="anime-title">Anime no Flickary.</h1>
            <p>Descubra filmes e séries de animação em idioma original japonês sem sair do catálogo que já conecta sua jornada.</p>
        </div>
        <div class="anime-hero__mark" aria-hidden="true"><span>ア</span></div>
        <nav class="search-filters" aria-label="Tipos de Anime">
            <?php foreach (['todos'=>'Todos','series'=>'Séries','filmes'=>'Filmes'] as $value => $label): ?>
                <a href="<?= e($filterUrl($value)) ?>" class="search-filter<?= $type === $value ? ' is-active' : '' ?>"<?= $type === $value ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </header>

    <?php foreach ($sections as $section): ?>
        <section class="search-results" aria-labelledby="anime-section-<?= e($section['type']) ?>">
            <header class="search-results__header">
                <div><p class="eyebrow"><?= $section['type'] === 'series' ? 'Televisão' : 'Cinema' ?></p><h2 id="anime-section-<?= e($section['type']) ?>"><?= e($section['title']) ?></h2></div>
                <?php if ($section['view_all_url'] !== null): ?><a href="<?= e($section['view_all_url']) ?>">Ver <?= $section['type'] === 'series' ? 'todas as séries' : 'todos os filmes' ?></a><?php endif; ?>
            </header>
            <?php if ($section['error'] !== null): ?>
                <div class="search-state search-state--error" role="status"><p><?= e($section['error']) ?></p></div>
            <?php elseif ($section['items'] === []): ?>
                <div class="search-state"><p>Nenhum título Anime foi encontrado nesta página.</p></div>
            <?php else: ?>
                <div class="media-grid"><?php foreach ($section['items'] as $item): ?><?php require dirname(__DIR__) . '/components/media-card.php'; ?><?php endforeach; ?></div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

    <?php if ($pagination !== null): ?>
        <?php if ($pagination['invalid']): ?><div class="search-state"><p>A página solicitada não está disponível.</p></div>
        <?php else: ?><nav class="pagination" aria-label="Paginação de Anime">
            <?= $pagination['previous_url'] !== null ? '<a rel="prev" href="' . e($pagination['previous_url']) . '">← Anterior</a>' : '<span aria-disabled="true">← Anterior</span>' ?>
            <strong>Página <?= e((string) $pagination['current']) ?> de <?= e((string) $pagination['total']) ?></strong>
            <?= $pagination['next_url'] !== null ? '<a rel="next" href="' . e($pagination['next_url']) . '">Próxima →</a>' : '<span aria-disabled="true">Próxima →</span>' ?>
        </nav><?php endif; ?>
    <?php endif; ?>

    <aside class="anime-policy" aria-labelledby="anime-policy-title">
        <p class="eyebrow">Classificação utilizada pelo Flickary</p>
        <h2 id="anime-policy-title">Uma categoria, o mesmo catálogo.</h2>
        <p>O Flickary identifica Anime dentro do catálogo TMDB como conteúdos de animação cujo idioma original é japonês. Anime continua sendo tecnicamente Filme ou Série.</p>
    </aside>
    <footer class="tmdb-compact-credit">Dados e imagens fornecidos por TMDB. <a href="/sobre#tmdb">Saiba mais sobre os créditos.</a></footer>
</section>
