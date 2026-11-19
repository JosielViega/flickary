<?php

declare(strict_types=1);

$journeyInitial = mb_strtoupper(mb_substr($journeyTitle, 0, 1));
?>
<a class="home-journey__card home-journey__card--<?= e(strtolower($journeyLabel)) ?>" href="<?= e($journeyUrl) ?>" aria-label="<?= e($journeyLabel . ': ' . $journeyTitle) ?>">
    <span class="home-journey__image"><span aria-hidden="true"><?= e($journeyInitial) ?></span><?php if ($journeyImageUrl !== null): ?><img src="<?= e($journeyImageUrl) ?>" alt="" loading="lazy" decoding="async" width="780" height="439"><?php endif; ?></span>
    <span class="home-journey__overlay"></span>
    <span class="home-journey__content"><small><?= e($journeyLabel) ?></small><strong><?= e($journeyTitle) ?></strong><span><?= e($journeySubtitle) ?></span></span>
</a>
