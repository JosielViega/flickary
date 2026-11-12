<?php

declare(strict_types=1);

/** @var null|\App\Integrations\Tmdb\TmdbMediaDetails $details */
/** @var null|string $posterUrl */
/** @var null|string $backdropUrl */
/** @var null|string $errorTitle */
/** @var null|string $errorMessage */
/** @var null|\App\Media\UserMediaItem $userMedia */
/** @var array<string,string> $statusOptions */
/** @var array<string,list<string>> $messages */
/** @var null|\App\Schedule\ScheduleEntry $scheduleItem */
/** @var null|\App\Preferences\UserMediaPreference $userPreference */
/** @var bool $isAnime */
?>
<?php foreach ($messages ?? [] as $type => $items): ?>
    <?php foreach ($items as $message): ?>
        <div class="flash flash--<?= $type === 'error' ? 'error' : 'success' ?>" role="status"><?= e($message) ?></div>
    <?php endforeach; ?>
<?php endforeach; ?>
<?php if ($details === null): ?>
    <section class="media-detail-state" aria-labelledby="media-detail-state-title">
        <p class="eyebrow">Catálogo Flickary</p>
        <div class="media-detail-state__code" aria-hidden="true"><?= $errorTitle === 'Título não encontrado' ? '404' : '—' ?></div>
        <h1 id="media-detail-state-title"><?= e($errorTitle ?? 'Detalhes indisponíveis') ?></h1>
        <p><?= e($errorMessage ?? 'Não foi possível carregar este título.') ?></p>
        <a class="media-detail__back" href="/buscar">← Voltar para buscar</a>
    </section>
<?php else: ?>
    <?php
    $typeLabel = $details->mediaType === 'movie' ? 'Filme' : 'Série';
    $initial = mb_strtoupper(mb_substr($details->title, 0, 1));
    $releaseDate = $details->releaseDate === null
        ? null
        : (new DateTimeImmutable($details->releaseDate))->format('d/m/Y');
    ?>
    <article class="media-detail">
        <section class="media-detail__hero" aria-labelledby="media-detail-title">
            <div class="media-detail__backdrop" aria-hidden="true">
                <?php if ($backdropUrl !== null): ?>
                    <img src="<?= e($backdropUrl) ?>" alt="" decoding="async" width="1280" height="720">
                <?php endif; ?>
            </div>

            <div class="media-detail__hero-content">
                <div class="media-detail__poster">
                    <span aria-hidden="true"><?= e($initial) ?></span>
                    <?php if ($posterUrl !== null): ?>
                        <img src="<?= e($posterUrl) ?>" alt="Pôster de <?= e($details->title) ?>" decoding="async" width="500" height="750">
                    <?php endif; ?>
                </div>

                <div class="media-detail__identity">
                    <p class="eyebrow"><?= $isAnime ? 'Anime · ' : '' ?><?= e($typeLabel) ?> · TMDB</p>
                    <h1 id="media-detail-title"><?= e($details->title) ?></h1>

                    <?php if ($details->originalTitle !== null && $details->originalTitle !== $details->title): ?>
                        <p class="media-detail__original">Título original: <?= e($details->originalTitle) ?></p>
                    <?php endif; ?>

                    <?php if ($details->tagline !== null): ?>
                        <p class="media-detail__tagline">“<?= e($details->tagline) ?>”</p>
                    <?php endif; ?>

                    <?php if ($details->genres !== []): ?>
                        <ul class="media-detail__genres" aria-label="Gêneros">
                            <?php foreach ($details->genres as $genre): ?>
                                <li><?= e($genre['name']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <dl class="media-detail__facts">
                        <?php if ($releaseDate !== null): ?>
                            <div><dt><?= $details->mediaType === 'movie' ? 'Lançamento' : 'Estreia' ?></dt><dd><?= e($releaseDate) ?></dd></div>
                        <?php elseif ($details->year !== null): ?>
                            <div><dt>Ano</dt><dd><?= e((string) $details->year) ?></dd></div>
                        <?php endif; ?>
                        <?php if ($details->runtime !== null): ?>
                            <div><dt>Duração</dt><dd><?= e((string) $details->runtime) ?> min</dd></div>
                        <?php endif; ?>
                        <?php if ($details->numberOfSeasons !== null): ?>
                            <div><dt>Temporadas</dt><dd><?= e((string) $details->numberOfSeasons) ?></dd></div>
                        <?php endif; ?>
                        <?php if ($details->numberOfEpisodes !== null): ?>
                            <div><dt>Episódios</dt><dd><?= e((string) $details->numberOfEpisodes) ?></dd></div>
                        <?php endif; ?>
                        <?php if ($details->voteAverage !== null): ?>
                            <div class="media-detail__rating">
                                <dt>Avaliação do catálogo</dt>
                                <dd>TMDB <?= e(number_format($details->voteAverage, 1, ',', '')) ?><?php if ($details->voteCount !== null): ?> <small>(<?= e(number_format($details->voteCount, 0, ',', '.')) ?> votos)</small><?php endif; ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </section>

        <section class="media-list-control" aria-labelledby="media-list-control-title">
            <div>
                <p class="eyebrow">Sua jornada</p>
                <h2 id="media-list-control-title"><?= $userMedia === null ? 'Guarde esta história.' : 'Na sua lista' ?></h2>
                <p><?= $userMedia === null ? 'Escolha como esta história faz parte do seu momento.' : 'Status atual: ' . e(\App\Media\UserMediaStatus::label($userMedia->status)) ?></p>
            </div>
            <?php if (isset($auth) && $auth instanceof \App\Core\Auth && $auth->check() && isset($csrf)): ?>
                <?php $basePath = $details->mediaType === 'movie' ? '/filmes/' : '/series/'; ?>
                <form class="media-list-control__form" method="post" action="<?= e($basePath . $details->sourceId . '/lista') ?>">
                    <?= $csrf->field() ?>
                    <label for="media-status">Estado atual</label>
                    <select id="media-status" name="status">
                        <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?= e($value) ?>"<?= ($userMedia?->status ?? 'planned') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="button button--primary" type="submit"><?= $userMedia === null ? 'Adicionar à Minha Lista' : 'Salvar status' ?></button>
                </form>
                <?php if ($userMedia !== null): ?>
                    <form method="post" action="<?= e($basePath . $details->sourceId . '/lista/remover') ?>">
                        <?= $csrf->field() ?>
                        <button class="button button--ghost" type="submit">Remover da Minha Lista</button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <a class="button button--primary" href="/login">Entre para adicionar à sua lista</a>
            <?php endif; ?>
        </section>

        <section class="preference-control" aria-labelledby="media-preference-title">
            <div>
                <p class="eyebrow">Sua opinião</p>
                <h2 id="media-preference-title">Favorito e avaliação pessoal</h2>
                <p>A sua nota é privada, independente da nota TMDB e não altera Minha Lista, Histórico ou Agenda.</p>
                <?php if ($userPreference?->rating !== null): ?><strong class="preference-control__summary">Sua nota <?= e((string)$userPreference->rating) ?>/10</strong><?php endif; ?>
            </div>
            <?php if (isset($auth) && $auth instanceof \App\Core\Auth && $auth->check() && isset($csrf)): ?>
                <?php $preferencePath = ($details->mediaType === 'movie' ? '/filmes/' : '/series/') . $details->sourceId . '/preferencias'; ?>
                <form class="preference-control__form" method="post" action="<?= e($preferencePath) ?>">
                    <?= $csrf->field() ?>
                    <label class="preference-control__favorite"><input type="checkbox" name="favorite" value="1"<?= $userPreference?->isFavorite ? ' checked' : '' ?>> Marcar como favorito</label>
                    <label for="personal-rating">Sua nota</label>
                    <select id="personal-rating" name="rating">
                        <option value="">Sem nota</option>
                        <?php for ($rating = 1; $rating <= 10; $rating++): ?><option value="<?= e((string)$rating) ?>"<?= $userPreference?->rating === $rating ? ' selected' : '' ?>><?= e((string)$rating) ?>/10</option><?php endfor; ?>
                    </select>
                    <button class="button button--primary" type="submit">Salvar preferências</button>
                </form>
            <?php else: ?>
                <a class="button button--primary" href="/login">Entre para favoritar e avaliar</a>
            <?php endif; ?>
        </section>

        <section class="schedule-register" aria-labelledby="media-schedule-title">
            <div>
                <p class="eyebrow">Agenda</p>
                <h2 id="media-schedule-title"><?= $scheduleItem === null ? 'Planeje esta história.' : 'Na sua Agenda' ?></h2>
                <p><?= $scheduleItem === null ? 'Escolha uma data. Isso não altera sua lista, progresso ou histórico.' : 'Agendado para ' . e((new DateTimeImmutable($scheduleItem->scheduledOn))->format('d/m/Y')) . '.' ?></p>
            </div>
            <?php if (isset($auth) && $auth->check()): ?>
                <?php $schedulePath = ($details->mediaType === 'movie' ? '/filmes/' : '/series/') . $details->sourceId . '/agenda'; ?>
                <form method="post" action="<?= e($schedulePath) ?>">
                    <?= $csrf->field() ?>
                    <label for="media-scheduled-on"><?= $scheduleItem === null ? 'Quando?' : 'Reagendar para' ?></label>
                    <input id="media-scheduled-on" type="date" name="scheduled_on" value="<?= e($scheduleItem?->scheduledOn ?? $today) ?>" min="<?= e($today) ?>" required>
                    <button class="button button--primary" type="submit"><?= $scheduleItem === null ? 'Adicionar à Agenda' : 'Reagendar' ?></button>
                </form>
                <?php if ($scheduleItem !== null): ?>
                    <form method="post" action="/agenda/<?= e((string) $scheduleItem->id) ?>/remover">
                        <?= $csrf->field() ?><button class="button button--ghost" type="submit">Remover da Agenda</button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <a class="button button--primary" href="/login">Entre para organizar sua agenda</a>
            <?php endif; ?>
        </section>

        <?php if ($details->mediaType === 'movie' && isset($auth) && $auth->check() && $historyRequestKey !== null): ?>
            <section class="history-register" aria-labelledby="movie-history-title">
                <div>
                    <p class="eyebrow">Histórico</p>
                    <h2 id="movie-history-title">Registrar visualização</h2>
                    <p>Guarde quando você assistiu. Isso não altera o estado na Minha Lista.</p>
                </div>
                <form method="post" action="/filmes/<?= e((string) $details->sourceId) ?>/historico">
                    <?= $csrf->field() ?>
                    <input type="hidden" name="request_key" value="<?= e($historyRequestKey) ?>">
                    <label for="movie-watched-on">Quando você assistiu?</label>
                    <input id="movie-watched-on" type="date" name="watched_on" value="<?= e($today) ?>" max="<?= e($today) ?>" required>
                    <button class="button button--primary" type="submit">Registrar visualização</button>
                </form>
            </section>
        <?php endif; ?>

        <section class="media-detail__overview" aria-labelledby="media-detail-overview-title">
            <p class="eyebrow">Sobre esta história</p>
            <h2 id="media-detail-overview-title">Sinopse</h2>
            <p><?= e($details->overview ?? 'Sinopse não disponível.') ?></p>
        </section>
        <?php if ($details->mediaType === 'series' && $details->seasons !== []): ?>
            <?php
            $mainTotal = 0;
            $mainWatched = 0;

            foreach ($details->seasons as $season) {
                if ($season->seasonNumber > 0) {
                    $mainTotal += $season->episodeCount;
                    $mainWatched += min(
                        $season->episodeCount,
                        $progressCounts[$season->seasonNumber] ?? 0,
                    );
                }
            }
            ?>
            <section class="season-list">
                <p class="eyebrow">Episódios</p>
                <h2>Temporadas</h2>
                <?php if (isset($auth) && $auth->check()): ?>
                    <p>
                        <?= e((string) $mainWatched) ?> de <?= e((string) $mainTotal) ?>
                        episódios principais marcados
                    </p>
                <?php endif; ?>
                <div class="season-list__grid">
                    <?php foreach ($details->seasons as $season): ?>
                        <a href="/series/<?= e((string) $details->sourceId) ?>/temporadas/<?= e((string) $season->seasonNumber) ?>">
                            <strong><?= e($season->name) ?></strong>
                            <span>
                                <?php if (isset($auth) && $auth->check()): ?>
                                    <?= e((string) ($progressCounts[$season->seasonNumber] ?? 0)) ?>
                                    de <?= e((string) $season->episodeCount) ?> marcados
                                <?php else: ?>
                                    <?= e((string) $season->episodeCount) ?> episódios
                                <?php endif; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <footer class="media-detail__footer">
            <a class="media-detail__back" href="/buscar">← Voltar para buscar</a>
            <p class="tmdb-compact-credit">Dados e imagens fornecidos por TMDB. <a href="/sobre#tmdb">Saiba mais sobre os créditos.</a></p>
        </footer>
    </article>
<?php endif; ?>
