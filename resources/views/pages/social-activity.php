<?php

declare(strict_types=1);

/** @var \App\Social\SocialActivityDetail $detail */
/** @var \App\Social\SocialActivityCommentPage $comments */
/** @var null|string $posterUrl */
/** @var string $commentBody */
/** @var null|string $commentError */

$activity = $detail->activity;
$isOwner = $detail->access === \App\Social\SocialActivityAccess::Owner;
$viewerId = $auth->id();
$isEpisode = $activity->entryType === 'episode';
$mediaUrl = $isEpisode
    ? '/series/' . $activity->sourceId . '/temporadas/' . (int) $activity->seasonNumber
    : '/filmes/' . $activity->sourceId;
$date = static function (string $value, string $format = 'd/m/Y'): string {
    try { return (new DateTimeImmutable($value))->format($format); } catch (Throwable) { return $value; }
};
$avatar = static function (?string $value): ?string {
    if ($value === null || strlen($value) > 2048 || filter_var($value, FILTER_VALIDATE_URL) === false) { return null; }
    return strtolower((string) parse_url($value, PHP_URL_SCHEME)) === 'https' ? $value : null;
};
$initial = static function (string $value): string {
    $letter = function_exists('mb_substr') ? mb_substr($value, 0, 1) : substr($value, 0, 1);
    return function_exists('mb_strtoupper') ? mb_strtoupper($letter) : strtoupper($letter);
};
?>
<section class="social-activity-page" aria-labelledby="activity-title">
    <a class="social-activity-page__back" href="/social">← Voltar ao Social</a>

    <article class="social-activity-detail">
        <header class="social-activity-detail__actor">
            <a class="social-activity-card__avatar" href="/u/<?= rawurlencode($activity->actorUsername) ?>" aria-label="Perfil de <?= e($activity->actorDisplayName) ?>">
                <span aria-hidden="true"><?= e($initial($activity->actorDisplayName !== '' ? $activity->actorDisplayName : $activity->actorUsername)) ?></span>
                <?php if (($actorAvatar = $avatar($activity->actorAvatarUrl)) !== null): ?>
                    <img src="<?= e($actorAvatar) ?>" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.hidden=true">
                <?php endif; ?>
            </a>
            <p><strong><?= e($activity->actorDisplayName) ?></strong><a href="/u/<?= rawurlencode($activity->actorUsername) ?>">@<?= e($activity->actorUsername) ?></a></p>
            <?php if ($isOwner): ?><span class="social-activity-detail__owner">Sua atividade</span><?php endif; ?>
        </header>

        <div class="social-activity-detail__media">
            <a class="social-activity-detail__poster" href="<?= e($mediaUrl) ?>">
                <?php if (is_string($posterUrl) && $posterUrl !== ''): ?>
                    <img src="<?= e($posterUrl) ?>" alt="Pôster de <?= e($activity->title) ?>" loading="lazy" decoding="async">
                <?php else: ?><span aria-hidden="true">F</span><?php endif; ?>
            </a>
            <div>
                <p class="eyebrow"><?= $activity->isAnime === true ? 'Anime · ' : '' ?><?= $isEpisode ? 'Série' : 'Filme' ?></p>
                <h1 id="activity-title"><?= e($activity->title) ?></h1>
                <?php if ($isEpisode): ?>
                    <p class="social-activity-detail__episode">T<?= e((string) $activity->seasonNumber) ?>E<?= e((string) $activity->episodeNumber) ?><?= is_string($activity->episodeTitle) && $activity->episodeTitle !== '' ? ' · ' . e($activity->episodeTitle) : '' ?></p>
                <?php endif; ?>
                <p>Assistiu em <?= e($date($activity->watchedOn)) ?></p>
                <dl class="social-activity-detail__counts">
                    <div><dt>Curtidas</dt><dd><?= e((string) $activity->likeCount) ?></dd></div>
                    <div><dt>Comentários</dt><dd><?= e((string) $activity->commentCount) ?></dd></div>
                </dl>
            </div>
        </div>
    </article>

    <?php if ($isOwner && !$detail->canComment): ?>
        <p class="social-activity-page__archive" role="status">Esta atividade não está sendo compartilhada no Social. Os comentários existentes continuam disponíveis para você.</p>
    <?php endif; ?>

    <?php if ($detail->canComment): ?>
        <section class="social-comment-form" aria-labelledby="comment-form-title">
            <div><p class="eyebrow">Participe da conversa</p><h2 id="comment-form-title">Escrever comentário</h2></div>
            <form method="post" action="/social/atividades/<?= e((string) $activity->historyId) ?>/comentarios" novalidate>
                <?= $csrf->field() ?>
                <label for="comment-body">Escrever comentário</label>
                <textarea id="comment-body" name="body" rows="5" maxlength="500" required aria-describedby="comment-hint<?= $commentError !== null ? ' comment-error' : '' ?>"<?= $commentError !== null ? ' aria-invalid="true"' : '' ?>><?= e($commentBody) ?></textarea>
                <p id="comment-hint">Texto simples, com até 500 caracteres.</p>
                <?php if ($commentError !== null): ?><p id="comment-error" class="profile-field__error" role="alert"><?= e($commentError) ?></p><?php endif; ?>
                <button class="button button--primary" type="submit">Publicar comentário</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="social-comments" aria-labelledby="comments-title">
        <header><p class="eyebrow">Conversa</p><h2 id="comments-title">Comentários · <?= e((string) $comments->total) ?></h2></header>
        <?php if ($comments->items === []): ?>
            <div class="social-comments__empty"><span aria-hidden="true">✦</span><p>Ainda não há comentários nesta atividade.</p></div>
        <?php else: ?>
            <ol class="social-comments__list">
                <?php foreach ($comments->items as $comment): ?>
                    <?php $commentAvatar = $avatar($comment->authorAvatarUrl); ?>
                    <li><article class="social-comment">
                        <header>
                            <a class="social-comment__avatar" href="/u/<?= rawurlencode($comment->authorUsername) ?>" aria-label="Perfil de <?= e($comment->authorDisplayName) ?>">
                                <span aria-hidden="true"><?= e($initial($comment->authorDisplayName !== '' ? $comment->authorDisplayName : $comment->authorUsername)) ?></span>
                                <?php if ($commentAvatar !== null): ?><img src="<?= e($commentAvatar) ?>" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.hidden=true"><?php endif; ?>
                            </a>
                            <p><a href="/u/<?= rawurlencode($comment->authorUsername) ?>"><strong><?= e($comment->authorDisplayName) ?></strong></a><span>@<?= e($comment->authorUsername) ?> · <?= e($date($comment->createdAt, 'd/m/Y H:i')) ?></span></p>
                            <?php if ($viewerId === $comment->authorUserId || $isOwner): ?>
                                <form method="post" action="/social/comentarios/<?= e((string) $comment->id) ?>/remover">
                                    <?= $csrf->field() ?><input type="hidden" name="context" value="activity"><button type="submit">Remover</button>
                                </form>
                            <?php endif; ?>
                        </header>
                        <p class="social-comment__body"><?= e($comment->body) ?></p>
                    </article></li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <?php if ($comments->totalPages() > 1): ?>
            <nav class="pagination" aria-label="Paginação dos comentários">
                <?= $comments->page > 1 ? '<a href="/social/atividades/' . $activity->historyId . '?page=' . ($comments->page - 1) . '">← Anterior</a>' : '<span aria-disabled="true">← Anterior</span>' ?>
                <strong>Página <?= $comments->page ?> de <?= $comments->totalPages() ?></strong>
                <?= $comments->page < $comments->totalPages() ? '<a href="/social/atividades/' . $activity->historyId . '?page=' . ($comments->page + 1) . '">Próxima →</a>' : '<span aria-disabled="true">Próxima →</span>' ?>
            </nav>
        <?php endif; ?>
    </section>
</section>
