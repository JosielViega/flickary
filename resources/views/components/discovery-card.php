<?php

declare(strict_types=1);

/** @var \App\Integrations\Tmdb\TmdbMedia|\App\Media\UserMediaItem $media */
/** @var null|string $posterUrl */
$isSnapshot = $media instanceof \App\Media\UserMediaItem;
$isAnime = $isSnapshot
    ? $media->isAnime === true
    : (new \App\Integrations\Tmdb\TmdbAnimeClassifier())->media($media);
$typeLabel = ($isAnime ? 'Anime · ' : '') . ($media->mediaType === 'movie' ? 'Filme' : 'Série');
$detailsUrl = $media->mediaType === 'movie' ? '/filmes/' . $media->sourceId : '/series/' . $media->sourceId;
$initial = mb_strtoupper(mb_substr($media->title, 0, 1));
$year = $isSnapshot ? $media->year() : $media->year;
$voteAverage = $isSnapshot ? null : $media->voteAverage;
?>
<article class="discovery-card">
    <a href="<?= e($detailsUrl) ?>" aria-label="Ver detalhes de <?= e($media->title) ?>">
        <div class="discovery-card__poster">
            <span class="discovery-card__fallback" aria-hidden="true"><?= e($initial) ?></span>
            <?php if ($posterUrl !== null): ?><img src="<?= e($posterUrl) ?>" alt="Pôster de <?= e($media->title) ?>" loading="lazy" decoding="async" width="342" height="513"><?php endif; ?>
            <span class="discovery-card__type"><?= e($typeLabel) ?></span>
        </div>
        <div class="discovery-card__body">
            <?php if (isset($contextLabel) && is_string($contextLabel)): ?><span class="discovery-card__context"><?= e($contextLabel) ?></span><?php endif; ?>
            <h3><?= e($media->title) ?></h3>
            <p>
                <?php if ($year !== null): ?><span><?= e((string) $year) ?></span><?php endif; ?>
                <?php if ($voteAverage !== null): ?><span aria-label="Nota TMDB <?= e(number_format($voteAverage, 1, ',', '')) ?>">TMDB <?= e(number_format($voteAverage, 1, ',', '')) ?></span><?php endif; ?>
            </p>
        </div>
    </a>
</article>
