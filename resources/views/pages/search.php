<?php

declare(strict_types=1);

/** @var string $query */
/** @var string $type */
/** @var bool $searched */
/** @var null|string $validationError */
/** @var list<array> $sections */
/** @var null|array $pagination */
/** @var string $discoveryFilter */
/** @var null|\App\Discovery\DiscoveryPage $discoveryPage */

$filterUrl = static fn (string $filter): string => '/buscar?' . http_build_query(
    ['q' => $query, 'tipo' => $filter],
    '',
    '&',
    PHP_QUERY_RFC3986,
);
$discoveryUrl = static fn (string $filter): string => '/buscar?' . http_build_query(
    ['descobrir' => $filter], '', '&', PHP_QUERY_RFC3986,
);
?>
<section class="search-page<?= !$searched ? ' search-page--discovery' : '' ?>" aria-labelledby="search-title">
    <header class="search-hero">
        <div>
            <p class="eyebrow">Descobrir histórias</p>
            <h1 id="search-title">O que você quer viver agora?</h1>
            <p>Pesquise filmes e séries no catálogo TMDB, sem criar uma conta.</p>
        </div>

        <form class="search-form" method="get" action="/buscar" role="search" data-partial-get>
            <label for="catalog-search">Filme ou série</label>
            <div class="search-form__field">
                <svg aria-hidden="true"><use href="#icon-search"/></svg>
                <input id="catalog-search" name="q" type="search" value="<?= e($query) ?>" placeholder="Ex.: Matrix, Breaking Bad..." minlength="2" maxlength="120" autocomplete="off">
                <?php if ($query !== '' && $type !== 'todos'): ?><input type="hidden" name="tipo" value="<?= e($type) ?>"><?php endif; ?>
                <button type="submit">Pesquisar</button>
            </div>
        </form>

        <?php if ($query === ''): ?>
            <div class="discovery-tabs">
                <span>Explorar</span>
                <nav class="search-filters" aria-label="Filtros de descoberta">
                    <?php foreach (['todos'=>'Todos','filmes'=>'Filmes','series'=>'Séries','anime'=>'Anime'] as $value => $label): ?>
                        <a href="<?= e($discoveryUrl($value)) ?>" class="search-filter<?= $discoveryFilter === $value ? ' is-active' : '' ?>"<?= $discoveryFilter === $value ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
                    <?php endforeach; ?>
                </nav>
            </div>
        <?php else: ?>
            <nav class="search-filters" aria-label="Tipos de conteúdo">
                <?php foreach (['todos' => 'Todos', 'filmes' => 'Filmes', 'series' => 'Séries'] as $value => $label): ?>
                    <a href="<?= e($filterUrl($value)) ?>" class="search-filter<?= $type === $value ? ' is-active' : '' ?>"<?= $type === $value ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
        <div class="search-discovery-links">
            <a class="search-anime-cta" href="/anime">Explorar Anime →</a>
            <a class="search-anime-cta" href="/usuarios">Encontrar pessoas →</a>
        </div>
    </header>

    <?php if ($validationError !== null): ?>
        <div class="search-state search-state--error" role="alert">
            <strong>Não foi possível pesquisar.</strong>
            <p><?= e($validationError) ?></p>
        </div>
    <?php elseif (!$searched): ?>
        <div class="discovery-page">
            <header class="discovery-page__intro">
                <p class="eyebrow">Descoberta de conteúdo</p>
                <h2>Histórias para começar agora.</h2>
                <p>Seleções editoriais e algorítmicas do catálogo público TMDB — sem personalização nesta versão.</p>
            </header>
            <?php if ($discoveryPage === null || !$discoveryPage->available()): ?>
                <div class="search-state search-state--error" role="status">
                    <strong>Não foi possível carregar as sugestões agora.</strong>
                    <p>Você ainda pode pesquisar um título.</p>
                </div>
            <?php else: ?>
                <?php foreach ($discoveryPage->shelves as $shelf): ?>
                    <?php if ($shelf->items === []) { continue; } ?>
                    <section class="discovery-shelf" aria-labelledby="discovery-<?= e($shelf->key) ?>">
                        <header class="discovery-shelf__header">
                            <div>
                                <h2 id="discovery-<?= e($shelf->key) ?>"><?= e($shelf->title) ?></h2>
                                <?php if ($shelf->subtitle !== null): ?><p><?= e($shelf->subtitle) ?></p><?php endif; ?>
                            </div>
                            <?php if ($shelf->ctaUrl !== null): ?><a href="<?= e($shelf->ctaUrl) ?>"><?= e($shelf->ctaLabel ?? 'Explorar') ?> →</a><?php endif; ?>
                        </header>
                        <div class="discovery-row" tabindex="0" aria-label="<?= e($shelf->title) ?>, rolagem horizontal">
                            <?php foreach ($shelf->items as $media): ?>
                                <?php $posterUrl = $discoveryPage->posterUrl($media->mediaType, $media->sourceId); require dirname(__DIR__) . '/components/discovery-card.php'; ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p class="search-summary">Resultados para <strong>“<?= e($query) ?>”</strong></p>

        <?php foreach ($sections as $section): ?>
            <section class="search-results" aria-labelledby="section-<?= e($section['type']) ?>">
                <header class="search-results__header">
                    <div>
                        <p class="eyebrow"><?= e($section['type'] === 'filmes' ? 'Cinema' : 'Televisão') ?></p>
                        <h2 id="section-<?= e($section['type']) ?>"><?= e($section['title']) ?></h2>
                    </div>
                    <?php if ($section['view_all_url'] !== null): ?>
                        <a href="<?= e($section['view_all_url']) ?>">Ver todos <?= e($section['type'] === 'filmes' ? 'os filmes' : 'as séries') ?></a>
                    <?php endif; ?>
                </header>

                <?php if ($section['error'] !== null): ?>
                    <div class="search-state search-state--error" role="status"><p><?= e($section['error']) ?></p></div>
                <?php elseif ($section['items'] === []): ?>
                    <div class="search-state"><p>Nenhum resultado encontrado para “<?= e($query) ?>”. Confira a escrita ou tente outro título.</p></div>
                <?php else: ?>
                    <div class="media-grid">
                        <?php foreach ($section['items'] as $item): ?>
                            <?php require dirname(__DIR__) . '/components/media-card.php'; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>

        <?php if ($pagination !== null): ?>
            <?php if ($pagination['invalid']): ?>
                <div class="search-state"><p>A página solicitada não está disponível.</p></div>
            <?php else: ?>
                <nav class="pagination" aria-label="Paginação dos resultados">
                    <?php if ($pagination['previous_url'] !== null): ?>
                        <a rel="prev" href="<?= e($pagination['previous_url']) ?>">← Anterior</a>
                    <?php else: ?><span aria-disabled="true">← Anterior</span><?php endif; ?>
                    <strong>Página <?= e((string) $pagination['current']) ?> de <?= e((string) $pagination['total']) ?></strong>
                    <?php if ($pagination['next_url'] !== null): ?>
                        <a rel="next" href="<?= e($pagination['next_url']) ?>">Próxima →</a>
                    <?php else: ?><span aria-disabled="true">Próxima →</span><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <footer class="tmdb-compact-credit">
        Dados e imagens fornecidos por TMDB. <a href="/sobre#tmdb">Saiba mais sobre os créditos.</a>
    </footer>
</section>
