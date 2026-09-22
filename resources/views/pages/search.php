<?php

declare(strict_types=1);

/** @var string $query */
/** @var string $type */
/** @var bool $searched */
/** @var null|string $validationError */
/** @var list<array> $sections */
/** @var null|array $pagination */

$filterUrl = static fn (string $filter): string => '/buscar?' . http_build_query(
    ['q' => $query, 'tipo' => $filter],
    '',
    '&',
    PHP_QUERY_RFC3986,
);
?>
<section class="search-page" aria-labelledby="search-title">
    <header class="search-hero">
        <div>
            <p class="eyebrow">Descobrir histórias</p>
            <h1 id="search-title">O que você quer viver agora?</h1>
            <p>Pesquise filmes e séries no catálogo TMDB, sem criar uma conta.</p>
        </div>

        <form class="search-form" method="get" action="/buscar" role="search">
            <label for="catalog-search">Filme ou série</label>
            <div class="search-form__field">
                <svg aria-hidden="true"><use href="#icon-search"/></svg>
                <input id="catalog-search" name="q" type="search" value="<?= e($query) ?>" placeholder="Ex.: Matrix, Breaking Bad..." minlength="2" maxlength="120" autocomplete="off" autofocus>
                <?php if ($type !== 'todos'): ?><input type="hidden" name="tipo" value="<?= e($type) ?>"><?php endif; ?>
                <button type="submit">Pesquisar</button>
            </div>
        </form>

        <nav class="search-filters" aria-label="Tipos de conteúdo">
            <?php foreach (['todos' => 'Todos', 'filmes' => 'Filmes', 'series' => 'Séries'] as $value => $label): ?>
                <a href="<?= e($filterUrl($value)) ?>" class="search-filter<?= $type === $value ? ' is-active' : '' ?>"<?= $type === $value ? ' aria-current="page"' : '' ?>>
                    <?= e($label) ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <a class="search-anime-cta" href="/anime">Explorar Anime →</a>
    </header>

    <?php if ($validationError !== null): ?>
        <div class="search-state search-state--error" role="alert">
            <strong>Não foi possível pesquisar.</strong>
            <p><?= e($validationError) ?></p>
        </div>
    <?php elseif (!$searched): ?>
        <div class="search-state search-state--initial">
            <span aria-hidden="true">⌕</span>
            <h2>Seu próximo título começa aqui.</h2>
            <p>Digite ao menos dois caracteres para explorar filmes e séries.</p>
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
