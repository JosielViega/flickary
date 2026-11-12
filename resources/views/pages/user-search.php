<?php /** @var ?\App\Social\PublicProfilePage $results */ ?>
<section class="people-page" aria-labelledby="people-search-title">
    <header class="people-page__header">
        <p class="eyebrow">Comunidade Flickary</p><h1 id="people-search-title">Encontrar pessoas</h1>
        <p>Busque por username ou nome de exibição.</p>
        <form class="people-search" method="get" action="/usuarios" role="search">
            <label class="visually-hidden" for="people-query">Username ou nome</label>
            <input id="people-query" type="search" name="q" value="<?= e($query) ?>" minlength="2" maxlength="100" placeholder="Ex.: @josiel ou Josiel" autocomplete="off">
            <button class="button button--primary" type="submit">Pesquisar</button>
        </form>
    </header>
    <?php if ($validationError !== null): ?><div class="people-empty" role="alert"><p><?= e($validationError) ?></p></div>
    <?php elseif ($results !== null && $results->items === []): ?><div class="people-empty"><h2>Nenhum perfil encontrado.</h2><p>Tente outro username ou nome.</p></div>
    <?php elseif ($results !== null): ?>
        <p class="people-page__summary"><?= e((string)$results->total) ?> perfil(is) para “<?= e($query) ?>”</p>
        <div class="user-grid"><?php foreach ($results->items as $user) { require dirname(__DIR__).'/components/user-card.php'; } ?></div>
        <?php if ($results->totalPages()>1): $base='/usuarios?'.http_build_query(['q'=>$query],'','&',PHP_QUERY_RFC3986); ?>
            <nav class="pagination" aria-label="Paginação dos perfis">
                <?= $results->page>1?'<a rel="prev" href="'.e($base.'&page='.($results->page-1)).'">← Anterior</a>':'<span aria-disabled="true">← Anterior</span>' ?>
                <strong>Página <?= e((string)$results->page) ?> de <?= e((string)$results->totalPages()) ?></strong>
                <?= $results->page<$results->totalPages()?'<a rel="next" href="'.e($base.'&page='.($results->page+1)).'">Próxima →</a>':'<span aria-disabled="true">Próxima →</span>' ?>
            </nav>
        <?php endif; ?>
    <?php else: ?><div class="people-empty"><h2>Histórias também conectam pessoas.</h2><p>Digite ao menos dois caracteres para começar.</p></div><?php endif; ?>
</section>
