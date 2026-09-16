<?php

declare(strict_types=1);

/** @var string $appName */
?>
<section class="home-hero" aria-labelledby="home-title">
    <div class="home-hero__copy">
        <p class="eyebrow">Passado · Presente · Futuro</p>
        <h1 id="home-title">Sua história <span>em movimento.</span></h1>
        <p class="home-hero__lead">Filmes. Séries. Animes. Sempre com você.</p>
        <p class="home-hero__description"><?= e($appName) ?> conecta as histórias que fizeram parte da sua vida, as que você acompanha agora e tudo o que ainda quer descobrir.</p>
        <ul class="media-types" aria-label="Conteúdos acompanhados">
            <li>Filmes</li>
            <li>Séries</li>
            <li>Animes</li>
        </ul>
    </div>

    <div class="home-hero__art" aria-hidden="true">
        <span class="story-frame story-frame--back"></span>
        <span class="story-frame story-frame--middle"></span>
        <span class="story-frame story-frame--front">
            <span class="story-frame__line"></span>
            <span class="story-frame__line story-frame__line--short"></span>
            <span class="story-frame__play"></span>
        </span>
        <span class="home-hero__glow"></span>
    </div>
</section>

<section class="journey" aria-labelledby="journey-title">
    <header class="section-heading">
        <div>
            <p class="eyebrow">Sua jornada</p>
            <h2 id="journey-title">Toda história encontra seu tempo.</h2>
        </div>
        <p>Um só lugar para lembrar, acompanhar e descobrir.</p>
    </header>

    <div class="journey-grid">
        <article class="journey-card journey-card--past">
            <div class="journey-card__topline">
                <span class="journey-card__index">01</span>
                <span class="journey-card__state">Memória</span>
            </div>
            <h3>Passado</h3>
            <p>As histórias que você concluiu, guardou e escolheu levar consigo.</p>
            <span class="journey-card__hint">Sua história</span>
        </article>

        <article class="journey-card journey-card--present">
            <div class="journey-card__topline">
                <span class="journey-card__index">02</span>
                <span class="journey-card__state">Agora</span>
            </div>
            <h3>Presente</h3>
            <p>O ritmo das narrativas que ainda estão acontecendo diante de você.</p>
            <span class="journey-card__hint">Continuidade</span>
        </article>

        <article class="journey-card journey-card--future">
            <div class="journey-card__topline">
                <span class="journey-card__index">03</span>
                <span class="journey-card__state">Descoberta</span>
            </div>
            <h3>Futuro</h3>
            <p>Novos mundos, próximas estreias e tudo o que você ainda quer viver.</p>
            <span class="journey-card__hint">O que vem pela frente</span>
        </article>
    </div>
</section>

<section class="manifesto" aria-labelledby="manifesto-title">
    <div class="manifesto__beam" aria-hidden="true"></div>
    <div class="manifesto__content">
        <p class="eyebrow">Mais que assistir</p>
        <h2 id="manifesto-title">Histórias que fizeram, fazem e ainda farão parte da sua vida.</h2>
        <p>O Flickary nasce para transformar listas em memória, continuidade e descoberta.</p>
    </div>
    <p class="manifesto__mark">Flickary <span>·</span> Viver histórias.</p>
</section>
