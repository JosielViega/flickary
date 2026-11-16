<?php

declare(strict_types=1);

/** @var array{title:string,text:string,url:string} $shareData */
?>
<div
    class="share-actions"
    data-share-actions
    data-share-title="<?= e($shareData['title']) ?>"
    data-share-text="<?= e($shareData['text']) ?>"
    data-share-url="<?= e($shareData['url']) ?>"
>
    <div class="share-actions__buttons">
        <button class="button button--secondary" type="button" data-share-native hidden>Compartilhar</button>
        <button class="button button--ghost" type="button" data-share-copy hidden>Copiar link</button>
    </div>
    <div class="share-actions__manual">
        <label>Link público
            <input type="url" value="<?= e($shareData['url']) ?>" readonly data-share-url-input>
        </label>
        <a href="<?= e($shareData['url']) ?>">Abrir link</a>
    </div>
    <p class="share-actions__status" role="status" aria-live="polite" data-share-status></p>
</div>
