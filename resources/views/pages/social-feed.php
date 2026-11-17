<?php

declare(strict_types=1);

/** @var \App\Social\SocialFeedPage $activities */
/** @var bool $followsAnyone */
/** @var array<int, null|string> $posterUrls */

$date = static function (string $value): string {
    try {
        return (new DateTimeImmutable($value))->format('d/m/Y');
    } catch (Throwable) {
        return $value;
    }
};
$avatar = static function (?string $value): ?string {
    if ($value === null || strlen($value) > 2048 || filter_var($value, FILTER_VALIDATE_URL) === false) {
        return null;
    }
    return strtolower((string) parse_url($value, PHP_URL_SCHEME)) === 'https' ? $value : null;
};
?>
<section class="social-feed" aria-labelledby="social-title">
    <header class="social-feed__hero">
        <div>
            <p class="eyebrow">Social</p>
            <h1 id="social-title">Histórias que estão sendo vividas.</h1>
            <p>Acompanhe as visualizações compartilhadas pelas pessoas que você segue.</p>
        </div>
        <nav class="social-feed__actions" aria-label="Ações do Social">
            <a class="button button--primary" href="/usuarios">Encontrar pessoas</a>
            <a class="button button--secondary" href="/mensagens">Mensagens</a>
            <a class="button button--secondary" href="/perfil/solicitacoes">Solicitações</a>
            <a class="button button--secondary" href="/notificacoes">Notificações</a>
        </nav>
    </header>

    <?php if ($activities->items === []): ?>
        <section class="social-feed__empty" aria-labelledby="social-empty-title">
            <span aria-hidden="true">✦</span>
            <?php if (!$followsAnyone): ?>
                <h2 id="social-empty-title">Seu Social ainda está vazio.</h2>
                <p>Encontre pessoas para acompanhar as histórias que elas compartilham.</p>
                <a class="button button--primary" href="/usuarios">Encontrar pessoas</a>
            <?php else: ?>
                <h2 id="social-empty-title">Ainda não há novas atividades compartilhadas.</h2>
                <p>As pessoas escolhem se desejam compartilhar suas visualizações.</p>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <ol class="social-feed__list" aria-label="Atividades compartilhadas">
            <?php foreach ($activities->items as $activity): ?>
                <?php
                $profileUrl = '/u/' . rawurlencode($activity->actorUsername);
                $isEpisode = $activity->entryType === 'episode';
                $detailUrl = $isEpisode
                    ? '/series/' . $activity->sourceId . '/temporadas/' . (int) $activity->seasonNumber
                    : '/filmes/' . $activity->sourceId;
                $initialSource = $activity->actorDisplayName !== '' ? $activity->actorDisplayName : $activity->actorUsername;
                $initial = function_exists('mb_substr') ? mb_substr($initialSource, 0, 1) : substr($initialSource, 0, 1);
                $initial = function_exists('mb_strtoupper') ? mb_strtoupper($initial) : strtoupper($initial);
                $avatarUrl = $avatar($activity->actorAvatarUrl);
                $posterUrl = $posterUrls[$activity->historyId] ?? null;
                $year = is_string($activity->contentDate) && preg_match('/^\d{4}/', $activity->contentDate) === 1
                    ? substr($activity->contentDate, 0, 4)
                    : null;
                ?>
                <li>
                    <article class="social-activity-card">
                        <header class="social-activity-card__actor">
                            <a class="social-activity-card__avatar" href="<?= e($profileUrl) ?>" aria-label="Perfil de <?= e($activity->actorDisplayName) ?>">
                                <span aria-hidden="true"><?= e($initial !== '' ? $initial : 'F') ?></span>
                                <?php if ($avatarUrl !== null): ?>
                                    <img src="<?= e($avatarUrl) ?>" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.hidden=true">
                                <?php endif; ?>
                            </a>
                            <p>
                                <a href="<?= e($profileUrl) ?>"><strong><?= e($activity->actorDisplayName) ?></strong></a>
                                <a href="<?= e($profileUrl) ?>">@<?= e($activity->actorUsername) ?></a>
                            </p>
                            <span>assistiu</span>
                        </header>

                        <a class="social-activity-card__media" href="<?= e($detailUrl) ?>">
                            <span class="social-activity-card__poster">
                                <?php if (is_string($posterUrl) && $posterUrl !== ''): ?>
                                    <img src="<?= e($posterUrl) ?>" alt="Pôster de <?= e($activity->title) ?>" loading="lazy" decoding="async">
                                <?php else: ?>
                                    <span aria-hidden="true">F</span>
                                <?php endif; ?>
                            </span>
                            <span class="social-activity-card__copy">
                                <strong><?= e($activity->title) ?></strong>
                                <?php if ($isEpisode): ?>
                                    <span class="social-activity-card__episode">
                                        T<?= e((string) $activity->seasonNumber) ?>E<?= e((string) $activity->episodeNumber) ?>
                                        <?php if (is_string($activity->episodeTitle) && $activity->episodeTitle !== ''): ?>
                                            · <?= e($activity->episodeTitle) ?>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                                <span class="social-activity-card__type">
                                    <?= $activity->isAnime === true ? 'Anime · ' : '' ?><?= $isEpisode ? 'Série' : 'Filme' ?><?= !$isEpisode && $year !== null ? ' · ' . e($year) : '' ?>
                                </span>
                                <span class="social-activity-card__date">Assistiu em <?= e($date($activity->watchedOn)) ?></span>
                            </span>
                        </a>
                        <footer class="social-activity-card__social">
                            <form method="post" action="/social/atividades/<?= e((string) $activity->historyId) ?>/<?= $activity->viewerLiked ? 'descurtir' : 'curtir' ?>">
                                <?= $csrf->field() ?>
                                <input type="hidden" name="page" value="<?= e((string) $activities->page) ?>">
                                <button
                                    class="social-activity-card__like<?= $activity->viewerLiked ? ' is-liked' : '' ?>"
                                    type="submit"
                                    aria-pressed="<?= $activity->viewerLiked ? 'true' : 'false' ?>"
                                    aria-label="<?= $activity->viewerLiked ? 'Descurtir' : 'Curtir' ?> <?= e($activity->title) ?>"
                                >
                                    <span aria-hidden="true"><?= $activity->viewerLiked ? '♥' : '♡' ?></span>
                                    <?= $activity->viewerLiked ? 'Curtido' : 'Curtir' ?> · <?= e((string) $activity->likeCount) ?>
                                </button>
                            </form>
                            <a class="social-activity-card__comments" href="/social/atividades/<?= e((string) $activity->historyId) ?>">
                                <?= $activity->commentCount === 0 ? 'Comentar' : 'Comentários · ' . e((string) $activity->commentCount) ?>
                            </a>
                        </footer>
                    </article>
                </li>
            <?php endforeach; ?>
        </ol>

        <?php if ($activities->totalPages() > 1): ?>
            <nav class="pagination" aria-label="Paginação do Social">
                <?php if ($activities->page > 1): ?>
                    <a href="/social?page=<?= $activities->page - 1 ?>">← Anterior</a>
                <?php else: ?>
                    <span aria-disabled="true">← Anterior</span>
                <?php endif; ?>
                <strong>Página <?= $activities->page ?> de <?= $activities->totalPages() ?></strong>
                <?php if ($activities->page < $activities->totalPages()): ?>
                    <a href="/social?page=<?= $activities->page + 1 ?>">Próxima →</a>
                <?php else: ?>
                    <span aria-disabled="true">Próxima →</span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
