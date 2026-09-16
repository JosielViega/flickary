<?php

declare(strict_types=1);

/** @var string $path */
?>
<section class="not-found" aria-labelledby="not-found-title">
    <div class="not-found__code" aria-hidden="true">404</div>
    <div class="not-found__content">
        <p class="eyebrow">Cena não encontrada</p>
        <h1 id="not-found-title">Essa história ainda não está aqui.</h1>
        <p>Não encontramos uma página para este caminho. Volte ao início e continue sua jornada.</p>
        <p class="not-found__path">Endereço: <code><?= e($path) ?></code></p>
        <a class="button button--primary" href="/">
            <svg aria-hidden="true"><use href="#icon-home"/></svg>
            Voltar ao início
        </a>
    </div>
</section>
