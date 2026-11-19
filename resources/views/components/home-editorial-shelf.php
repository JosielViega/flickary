<?php

declare(strict_types=1);
?>
<?php if ($shelfItems !== []): ?>
<section class="home-shelf" aria-labelledby="<?= e($shelfId) ?>">
    <header><div><p class="eyebrow">Catálogo</p><h2 id="<?= e($shelfId) ?>"><?= e($shelfTitle) ?></h2></div><a href="/buscar">Explorar</a></header>
    <div class="home-shelf__track" tabindex="0" aria-label="<?= e($shelfTitle) ?>">
        <?php foreach ($shelfItems as $media): $posterUrl = $editorial->posterUrl($media); unset($contextLabel); require __DIR__ . '/discovery-card.php'; endforeach; ?>
    </div>
</section>
<?php endif; ?>
