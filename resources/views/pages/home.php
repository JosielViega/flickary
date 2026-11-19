<?php

declare(strict_types=1);

/** @var null|\App\Home\HomeEditorialPage $editorial */
/** @var null|\App\Home\HomePersonalPage $personal */
$hero = $editorial?->hero;
$authenticated = isset($auth) && $auth instanceof \App\Core\Auth && $auth->check();
$heroUrl = $hero === null ? '/buscar' : ($hero->mediaType === 'movie' ? '/filmes/' : '/series/') . $hero->sourceId;
$heroImageUrl = $hero === null ? null : ($editorial->backdropUrl($hero) ?? $editorial->posterUrl($hero));
$heroIsAnime = $hero === null ? false : (new \App\Integrations\Tmdb\TmdbAnimeClassifier())->media($hero);
$heroType = $hero === null ? null : ($heroIsAnime ? 'Anime · ' : '') . ($hero->mediaType === 'movie' ? 'Filme' : 'Série');
$editorialJourney = $editorial?->journeyItems() ?? [];
?>
<section class="home-feature<?= $hero === null ? ' home-feature--fallback' : '' ?>" aria-labelledby="home-title">
    <?php if ($heroImageUrl !== null): ?><img class="home-feature__image" src="<?= e($heroImageUrl) ?>" alt="" decoding="async" width="1280" height="720"><?php endif; ?>
    <div class="home-feature__shade"></div>
    <div class="home-feature__content">
        <p class="eyebrow"><?= $hero === null ? 'Passado · Presente · Futuro' : 'Em destaque' ?></p>
        <h1 id="home-title"><?= e($hero?->title ?? 'Sua história em movimento.') ?></h1>
        <?php if ($hero !== null): ?>
            <p class="home-feature__meta"><?php if ($hero->year !== null): ?><span><?= e((string) $hero->year) ?></span><?php endif; ?><span><?= e($heroType ?? '') ?></span><?php if ($hero->voteAverage !== null): ?><span>TMDB <?= e(number_format($hero->voteAverage, 1, ',', '')) ?></span><?php endif; ?></p>
            <?php if ($hero->overview !== null): ?><p class="home-feature__overview"><?= e($hero->overview) ?></p><?php endif; ?>
        <?php else: ?><p class="home-feature__overview">Filmes. Séries. Animes.</p><?php endif; ?>
        <div class="home-feature__actions"><a class="button button--primary" href="<?= e($heroUrl) ?>"><?= $hero === null ? 'Explorar catálogo' : 'Ver detalhes' ?></a><?php if ($hero !== null): ?><a class="button button--ghost" href="/buscar">Explorar</a><?php endif; ?></div>
    </div>
</section>

<section class="home-journey" aria-labelledby="home-journey-title">
    <header><p class="eyebrow">Passado · Presente · Futuro</p><h2 id="home-journey-title"><?= $authenticated ? 'Sua jornada agora' : 'Histórias em cada tempo' ?></h2></header>
    <div class="home-journey__grid">
        <?php
        if ($personal?->history[0] ?? null) {
            $item = $personal->history[0]; $journeyLabel = 'Passado'; $journeyTitle = $item->title; $journeySubtitle = 'Assistido em ' . (new DateTimeImmutable($item->watchedOn))->format('d/m/Y'); $journeyUrl = '/historico'; $journeyImageUrl = $personal->historyPosterUrls[$item->id] ?? null;
        } else {
            $item = $editorialJourney[0] ?? null; $journeyLabel = 'Passado'; $journeyTitle = 'Histórias para lembrar'; $journeySubtitle = $item?->title ?? 'Sua história em movimento'; $journeyUrl = $authenticated ? '/historico' : '/buscar'; $journeyImageUrl = $item === null ? null : ($editorial?->backdropUrl($item) ?? $editorial?->posterUrl($item));
        }
        require dirname(__DIR__) . '/components/home-journey-card.php';
        if ($personal?->watching[0] ?? null) {
            $item = $personal->watching[0]; $journeyLabel = 'Presente'; $journeyTitle = $item->title; $journeySubtitle = 'Assistindo'; $journeyUrl = '/minha-lista?status=watching'; $journeyImageUrl = $personal->watchingBackdropUrls[$item->id] ?? $personal->watchingPosterUrls[$item->id] ?? null;
        } else {
            $item = $editorialJourney[1] ?? null; $journeyLabel = 'Presente'; $journeyTitle = $authenticated ? 'O que acontece agora' : 'O que está em alta'; $journeySubtitle = $item?->title ?? 'Filmes, séries e animes'; $journeyUrl = $authenticated ? '/minha-lista?status=watching' : '/buscar'; $journeyImageUrl = $item === null ? null : ($editorial?->backdropUrl($item) ?? $editorial?->posterUrl($item));
        }
        require dirname(__DIR__) . '/components/home-journey-card.php';
        if ($personal?->agenda[0] ?? null) {
            $item = $personal->agenda[0]; $journeyLabel = 'Futuro'; $journeyTitle = $item->title; $journeySubtitle = ($item->scheduledOn === date('Y-m-d') ? 'Hoje' : (new DateTimeImmutable($item->scheduledOn))->format('d/m/Y')) . ($item->episodeTitle === null ? '' : ' · ' . $item->episodeTitle); $journeyUrl = '/agenda'; $journeyImageUrl = $personal->agendaPosterUrls[$item->id] ?? null;
        } else {
            $item = $editorialJourney[2] ?? null; $journeyLabel = 'Futuro'; $journeyTitle = $authenticated ? 'Próximas histórias' : 'O que vem por aí'; $journeySubtitle = $item?->title ?? 'Novas descobertas'; $journeyUrl = $authenticated ? '/agenda' : '/buscar'; $journeyImageUrl = $item === null ? null : ($editorial?->backdropUrl($item) ?? $editorial?->posterUrl($item));
        }
        require dirname(__DIR__) . '/components/home-journey-card.php';
        ?>
    </div>
    <?php if (!$authenticated): ?><a class="home-journey__login" href="/login">Entre para guardar sua jornada</a><?php endif; ?>
</section>

<?php if ($personal !== null && $personal->watching !== []): ?>
<section class="home-shelf" aria-labelledby="home-watching-title"><header><div><p class="eyebrow">Em andamento</p><h2 id="home-watching-title">Continue sua jornada</h2></div><a href="/minha-lista?status=watching">Ver Minha Lista</a></header><div class="home-shelf__track" tabindex="0" aria-label="Continue sua jornada"><?php foreach ($personal->watching as $media): $posterUrl = $personal->watchingPosterUrls[$media->id] ?? null; $contextLabel = 'Assistindo'; require dirname(__DIR__) . '/components/discovery-card.php'; endforeach; unset($contextLabel); ?></div></section>
<?php endif; ?>

<?php if ($personal !== null && $personal->agenda !== []): ?>
<section class="home-agenda" aria-labelledby="home-agenda-title"><header><div><p class="eyebrow">Próximas histórias</p><h2 id="home-agenda-title">Sua Agenda</h2></div><a href="/agenda">Ver agenda</a></header><div class="home-agenda__grid"><?php foreach ($personal->agenda as $item): ?><a class="home-agenda__card" href="/agenda"><span class="home-agenda__poster"><span aria-hidden="true"><?= e(mb_strtoupper(mb_substr($item->title, 0, 1))) ?></span><?php if (isset($personal->agendaPosterUrls[$item->id])): ?><img src="<?= e($personal->agendaPosterUrls[$item->id]) ?>" alt="Pôster de <?= e($item->title) ?>" loading="lazy" decoding="async" width="185" height="278"><?php endif; ?></span><span><small><?= $item->scheduledOn === date('Y-m-d') ? 'Hoje' : e((new DateTimeImmutable($item->scheduledOn))->format('d/m')) ?></small><strong><?= e($item->title) ?></strong><?php if ($item->episodeNumber > 0): ?><span>T<?= e((string) $item->seasonNumber) ?>:E<?= e((string) $item->episodeNumber) ?><?= $item->episodeTitle === null ? '' : ' · ' . e($item->episodeTitle) ?></span><?php endif; ?></span></a><?php endforeach; ?></div></section>
<?php endif; ?>

<?php if ($editorial !== null): $shelfId = 'home-trending-title'; $shelfTitle = 'Em alta agora'; $shelfItems = $editorial->trending; require dirname(__DIR__) . '/components/home-editorial-shelf.php'; endif; ?>

<?php if ($personal !== null && $personal->history !== []): ?>
<section class="home-history" aria-labelledby="home-history-title"><header><div><p class="eyebrow">Visto recentemente</p><h2 id="home-history-title">Histórico recente</h2></div><a href="/historico">Ver histórico</a></header><div class="home-history__track" tabindex="0" aria-label="Histórico recente"><?php foreach ($personal->history as $item): ?><a class="home-history__card" href="/historico"><span class="home-history__poster"><span aria-hidden="true"><?= e(mb_strtoupper(mb_substr($item->title, 0, 1))) ?></span><?php if (isset($personal->historyPosterUrls[$item->id])): ?><img src="<?= e($personal->historyPosterUrls[$item->id]) ?>" alt="Pôster de <?= e($item->title) ?>" loading="lazy" decoding="async" width="185" height="278"><?php endif; ?></span><strong><?= e($item->title) ?></strong><?php if ($item->episodeNumber !== null): ?><span>T<?= e((string) $item->seasonNumber) ?>:E<?= e((string) $item->episodeNumber) ?></span><?php endif; ?><small><?= e((new DateTimeImmutable($item->watchedOn))->format('d/m/Y')) ?></small></a><?php endforeach; ?></div></section>
<?php endif; ?>

<?php if ($editorial !== null): $shelfId = 'home-launches-title'; $shelfTitle = 'Lançamentos'; $shelfItems = $editorial->launches; require dirname(__DIR__) . '/components/home-editorial-shelf.php'; endif; ?>

<?php if (($editorial?->trendingUnavailable ?? false) && ($editorial?->launchesUnavailable ?? false)): ?><p class="home-catalog-note">O catálogo está temporariamente indisponível. Sua jornada continua por aqui.</p><?php endif; ?>
<p class="tmdb-compact-credit">Dados e imagens fornecidos por TMDB. <a href="/sobre#tmdb">Saiba mais sobre os créditos.</a></p>
