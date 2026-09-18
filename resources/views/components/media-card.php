<?php

declare(strict_types=1);

/** @var array{media:\App\Integrations\Tmdb\TmdbMedia,poster_url:?string} $item */
$media = $item['media'];
$posterUrl = $item['poster_url'];
$typeLabel = $media->mediaType === 'movie' ? 'Filme' : 'Série';
$initial = mb_strtoupper(mb_substr($media->title, 0, 1));
$detailsUrl = match (true) {
    $media->source === 'tmdb' && $media->mediaType === 'movie' => '/filmes/' . $media->sourceId,
    $media->source === 'tmdb' && $media->mediaType === 'series' => '/series/' . $media->sourceId,
    default => null,
};
?>
<article class="media-card">
    <?php if ($detailsUrl !== null): ?><a class="media-card__link" href="<?= e($detailsUrl) ?>" aria-label="Ver detalhes de <?= e($media->title) ?>"><?php endif; ?>
        <div class="media-card__poster">
            <span class="media-card__fallback" aria-hidden="true"><?= e($initial) ?></span>
            <?php if ($posterUrl !== null): ?>
                <img src="<?= e($posterUrl) ?>" alt="Pôster de <?= e($media->title) ?>" loading="lazy" decoding="async" width="500" height="750">
            <?php endif; ?>
            <span class="media-card__type"><?= e($typeLabel) ?></span>
        </div>
        <div class="media-card__body">
            <h3><?= e($media->title) ?></h3>
            <div class="media-card__meta">
                <?php if ($media->year !== null): ?><span><?= e((string) $media->year) ?></span><?php endif; ?>
                <?php if ($media->voteAverage !== null): ?>
                    <span class="media-card__rating" aria-label="Nota TMDB <?= e(number_format($media->voteAverage, 1, ',', '')) ?>">
                        TMDB <?= e(number_format($media->voteAverage, 1, ',', '')) ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php if ($media->overview !== null): ?>
                <p class="media-card__overview"><?= e($media->overview) ?></p>
            <?php endif; ?>
        </div>
    <?php if ($detailsUrl !== null): ?></a><?php endif; ?>
</article>
