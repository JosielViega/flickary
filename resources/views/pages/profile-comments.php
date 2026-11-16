<?php

declare(strict_types=1);

/** @var \App\Social\SocialOwnCommentPage $comments */
$date = static function (string $value): string {
    try { return (new DateTimeImmutable($value))->format('d/m/Y H:i'); } catch (Throwable) { return $value; }
};
?>
<section class="profile-comments-page" aria-labelledby="profile-comments-title">
    <header class="profile-comments-page__hero">
        <div><p class="eyebrow">Seu conteúdo</p><h1 id="profile-comments-title">Meus comentários</h1><p>Revise ou remova o que você publicou no Social, mesmo quando uma atividade não estiver mais acessível.</p></div>
        <a class="button button--secondary" href="/perfil">Voltar ao perfil</a>
    </header>

    <?php if ($comments->items === []): ?>
        <section class="profile-comments-page__empty"><span aria-hidden="true">✦</span><h2>Nenhum comentário por aqui.</h2><p>Quando você participar de uma atividade, seu comentário aparecerá nesta área privada.</p></section>
    <?php else: ?>
        <ol class="profile-comments-list">
            <?php foreach ($comments->items as $comment): ?>
                <li><article class="profile-comment-card">
                    <header><div><span><?= $comment->entryType === 'episode' ? 'Série' : 'Filme' ?></span><strong><?= e($comment->activityTitle) ?></strong><?php if ($comment->entryType === 'episode'): ?><small>T<?= e((string) $comment->seasonNumber) ?>E<?= e((string) $comment->episodeNumber) ?><?= is_string($comment->episodeTitle) && $comment->episodeTitle !== '' ? ' · ' . e($comment->episodeTitle) : '' ?></small><?php endif; ?></div><span>@<?= e($comment->actorUsername) ?></span></header>
                    <p class="profile-comment-card__body"><?= e($comment->body) ?></p>
                    <footer><time><?= e($date($comment->createdAt)) ?></time><form method="post" action="/social/comentarios/<?= e((string) $comment->id) ?>/remover"><?= $csrf->field() ?><input type="hidden" name="context" value="profile"><button type="submit">Remover comentário</button></form></footer>
                </article></li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>

    <?php if ($comments->totalPages() > 1): ?>
        <nav class="pagination" aria-label="Paginação dos meus comentários">
            <?= $comments->page > 1 ? '<a href="/perfil/comentarios?page=' . ($comments->page - 1) . '">← Anterior</a>' : '<span aria-disabled="true">← Anterior</span>' ?>
            <strong>Página <?= $comments->page ?> de <?= $comments->totalPages() ?></strong>
            <?= $comments->page < $comments->totalPages() ? '<a href="/perfil/comentarios?page=' . ($comments->page + 1) . '">Próxima →</a>' : '<span aria-disabled="true">Próxima →</span>' ?>
        </nav>
    <?php endif; ?>
</section>
