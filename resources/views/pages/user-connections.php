<?php
/** @var \App\Social\PublicProfile $profile */
/** @var \App\Social\PublicProfilePage $profiles */
$label = $connectionType === 'followers' ? 'Seguidores' : 'Seguindo';
$base = '/u/' . rawurlencode($profile->username) . '/' . ($connectionType === 'followers' ? 'seguidores' : 'seguindo');
?>
<section class="people-page" aria-labelledby="connections-title">
    <header class="people-page__header">
        <a href="/u/<?= rawurlencode($profile->username) ?>">← Voltar para @<?= e($profile->username) ?></a>
        <p class="eyebrow">Conexões</p><h1 id="connections-title"><?= e($label) ?></h1>
        <p><?= e((string)$profiles->total) ?> <?= mb_strtolower($label) ?></p>
    </header>
    <?php if ($profiles->items === []): ?>
        <div class="people-empty"><h2>Nenhuma conexão por aqui.</h2><p>Esta parte da história ainda está começando.</p></div>
    <?php else: ?>
        <div class="user-grid"><?php foreach ($profiles->items as $user) { require dirname(__DIR__).'/components/user-card.php'; } ?></div>
    <?php endif; ?>
    <?php if ($profiles->totalPages() > 1): ?>
        <nav class="pagination" aria-label="Paginação de <?= e(mb_strtolower($label)) ?>">
            <?= $profiles->page > 1 ? '<a rel="prev" href="'.e($base.'?page='.($profiles->page-1)).'">← Anterior</a>' : '<span aria-disabled="true">← Anterior</span>' ?>
            <strong>Página <?= e((string)$profiles->page) ?> de <?= e((string)$profiles->totalPages()) ?></strong>
            <?= $profiles->page < $profiles->totalPages() ? '<a rel="next" href="'.e($base.'?page='.($profiles->page+1)).'">Próxima →</a>' : '<span aria-disabled="true">Próxima →</span>' ?>
        </nav>
    <?php endif; ?>
</section>
