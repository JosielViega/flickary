<?php
/** @var \App\Social\PublicProfile $profile */
/** @var ?\App\Social\PublicProfileStatistics $statistics */
$initial = mb_strtoupper(mb_substr($profile->displayName !== '' ? $profile->displayName : $profile->username, 0, 1));
$joined = $profile->createdAt;
try { $joined = (new DateTimeImmutable($joined))->format('d/m/Y'); } catch (Throwable) {}
$hours = $statistics === null ? 0 : intdiv($statistics->knownMinutes, 60);
$minutes = $statistics === null ? 0 : $statistics->knownMinutes % 60;
?>
<section class="public-profile" aria-labelledby="public-profile-title">
    <?php foreach (($messages['success'] ?? []) as $message): ?>
        <p class="form-alert form-alert--success" role="status"><?= e((string) $message) ?></p>
    <?php endforeach; ?>

    <article class="public-profile__hero">
        <div class="public-profile__cover">
            <span class="public-profile__beam" aria-hidden="true"></span>
            <span class="public-profile__frames" aria-hidden="true"></span>
            <span class="public-profile__signature">Passado · Presente · Futuro</span>
            <?php if ($profile->coverUrl !== null): ?>
                <img src="<?= e($profile->coverUrl) ?>" alt="" loading="eager" decoding="async" referrerpolicy="no-referrer" onerror="this.hidden=true">
            <?php endif; ?>
        </div>
        <div class="public-profile__identity">
            <div class="public-profile__avatar" aria-label="Avatar de <?= e($profile->displayName) ?>">
                <span aria-hidden="true"><?= e($initial !== '' ? $initial : 'F') ?></span>
                <?php if ($profile->avatarUrl !== null): ?>
                    <img src="<?= e($profile->avatarUrl) ?>" alt="Avatar de <?= e($profile->displayName) ?>" referrerpolicy="no-referrer" onerror="this.hidden=true">
                <?php endif; ?>
            </div>
            <div class="public-profile__copy">
                <p class="eyebrow"><?= $isOwner ? 'Visualização do seu perfil' : 'Perfil Flickary' ?></p>
                <h1 id="public-profile-title"><?= e($profile->displayName) ?></h1>
                <p class="public-profile__handle">@<?= e($profile->username) ?></p>
                <?php if ($profile->bio !== null && $profile->bio !== ''): ?>
                    <p class="public-profile__bio"><?= nl2br(e($profile->bio)) ?></p>
                <?php else: ?><p class="public-profile__bio public-profile__bio--empty">Esta jornada ainda está começando.</p><?php endif; ?>
            </div>
            <div class="public-profile__action">
                <?php if ($isOwner): ?>
                    <a class="button button--secondary" href="/perfil">Editar perfil</a>
                <?php elseif (!$auth->check()): ?>
                    <a class="button button--primary" href="/login">Entre para seguir</a>
                <?php else: ?>
                    <form method="post" action="/u/<?= rawurlencode($profile->username) ?>/<?= $isFollowing ? 'deixar-de-seguir' : 'seguir' ?>">
                        <?= $csrf->field() ?>
                        <button class="button <?= $isFollowing ? 'button--secondary' : 'button--primary' ?>" type="submit"><?= $isFollowing ? 'Deixar de seguir' : 'Seguir' ?></button>
                    </form>
                <?php endif; ?>
                <?php if (is_array($shareData ?? null)): ?>
                    <?php require dirname(__DIR__) . '/components/share-actions.php'; ?>
                <?php endif; ?>
            </div>
            <div class="public-profile__social">
                <a href="/u/<?= rawurlencode($profile->username) ?>/seguidores"><strong><?= e((string) $followersCount) ?></strong><span>Seguidores</span></a>
                <a href="/u/<?= rawurlencode($profile->username) ?>/seguindo"><strong><?= e((string) $followingCount) ?></strong><span>Seguindo</span></a>
                <span><strong><?= e($joined) ?></strong><span>Na jornada desde</span></span>
            </div>
        </div>
    </article>

    <?php if ($profile->isPrivate && $isOwner): ?>
        <div class="public-profile__notice" role="status"><strong>Seu perfil está privado.</strong> Outras pessoas não veem os conteúdos exibidos nesta prévia.</div>
    <?php elseif ($profile->isPrivate): ?>
        <section class="public-profile__locked" aria-labelledby="private-profile-title">
            <span aria-hidden="true">●</span><div><p class="eyebrow">Conteúdo reservado</p><h2 id="private-profile-title">Perfil privado</h2>
            <p>Favoritos, coleção e estatísticas pessoais não estão visíveis. Seguir este perfil não libera o conteúdo privado.</p></div>
        </section>
    <?php endif; ?>

    <?php if ($canViewContent && $statistics !== null): ?>
        <section class="public-profile__section" aria-labelledby="journey-title">
            <header><div><p class="eyebrow">Visão geral</p><h2 id="journey-title">Uma jornada entre histórias.</h2></div></header>
            <div class="public-stats">
                <article><strong><?= e((string) $statistics->libraryItems) ?></strong><span>Na coleção</span></article>
                <article><strong><?= e((string) $statistics->favoriteItems) ?></strong><span>Favoritos</span></article>
                <article><strong><?= e((string) $statistics->ratedItems) ?></strong><span>Avaliados</span></article>
                <article><strong><?= $statistics->averageRating === null ? '—' : e(number_format($statistics->averageRating, 1, ',', '.')) ?></strong><span>Nota média</span></article>
                <article><strong><?= e((string) $statistics->historyViews) ?></strong><span>Visualizações</span></article>
                <article><strong><?= e($hours > 0 ? $hours . 'h ' . $minutes . 'min' : $minutes . 'min') ?></strong><span>Tempo registrado<?= $statistics->unknownDurationViews > 0 ? ' *' : '' ?></span></article>
            </div>
            <?php if ($statistics->unknownDurationViews > 0): ?><p class="public-stats__note">* <?= e((string) $statistics->unknownDurationViews) ?> visualização(ões) não possui(em) duração conhecida.</p><?php endif; ?>
        </section>

        <section class="public-profile__section" aria-labelledby="favorites-title">
            <header><div><p class="eyebrow">Seleção pessoal</p><h2 id="favorites-title">Favoritos</h2></div></header>
            <?php if ($favorites === []): ?>
                <div class="public-profile__empty"><p>A jornada ainda está começando.</p></div>
            <?php else: ?>
                <div class="public-favorites">
                    <?php foreach ($favorites as $favorite): $posterKey=$favorite->mediaType.':'.$favorite->sourceId; ?>
                        <a class="public-favorite" href="<?= e($favorite->url()) ?>">
                            <span class="public-favorite__poster">
                                <span aria-hidden="true">F</span>
                                <?php if (is_string($posterUrls[$posterKey] ?? null)): ?><img src="<?= e($posterUrls[$posterKey]) ?>" alt="" loading="lazy" decoding="async"><?php endif; ?>
                            </span>
                            <span class="public-favorite__copy"><strong><?= e($favorite->title) ?></strong>
                                <small><?= $favorite->isAnime ? 'Anime' : ($favorite->mediaType === 'movie' ? 'Filme' : 'Série') ?><?= $favorite->year() ? ' · '.e((string)$favorite->year()) : '' ?></small>
                                <?php if ($favorite->rating !== null): ?><b>Sua nota <?= e((string)$favorite->rating) ?>/10</b><?php endif; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>
