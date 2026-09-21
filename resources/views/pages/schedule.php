<?php

declare(strict_types=1);

/** @var \App\Schedule\SchedulePage $pageData */
foreach ($messages as $type => $items): foreach ($items as $message): ?>
    <div class="flash flash--<?= $type === 'error' ? 'error' : 'success' ?>" role="status"><?= e($message) ?></div>
<?php endforeach; endforeach; ?>

<section class="schedule-page" aria-labelledby="schedule-title">
    <header class="schedule-header">
        <div><p class="eyebrow">Futuro explícito</p><h1 id="schedule-title">Agenda</h1><p>Planos escolhidos por você, sem alterar sua lista, progresso ou histórico.</p></div>
        <a class="button button--primary" href="/buscar">Explorar títulos</a>
    </header>
    <form class="schedule-filters" method="get" action="/agenda">
        <label for="schedule-type">Tipo</label>
        <select id="schedule-type" name="tipo">
            <?php foreach (['todos'=>'Todos','filmes'=>'Filmes','series'=>'Séries','episodios'=>'Episódios'] as $value=>$label): ?>
                <option value="<?= e($value) ?>"<?= $typeFilter === $value ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select><button class="button button--ghost" type="submit">Filtrar</button>
    </form>
    <?php if ($pageData->items === []): ?>
        <div class="schedule-empty"><h2><?= $hasAnyItems ? 'Nenhum agendamento corresponde a este filtro.' : 'Sua agenda ainda está vazia.' ?></h2>
        <a class="button button--primary" href="<?= $hasAnyItems ? '/agenda' : '/buscar' ?>"><?= $hasAnyItems ? 'Limpar filtro' : 'Explorar títulos' ?></a></div>
    <?php else: ?>
        <?php $lastGroup = null; ?>
        <div class="schedule-groups">
        <?php foreach ($pageData->items as $item): ?>
            <?php $state=$item->state($today); $group=$state==='today'?'Hoje':($state==='overdue'?'Atrasados':(new DateTimeImmutable($item->scheduledOn))->format('d/m/Y')); ?>
            <?php if ($group !== $lastGroup): $lastGroup=$group; ?><h2 class="schedule-group-title"><?= e($group) ?></h2><?php endif; ?>
            <?php $path=$item->entryType==='movie'?'/filmes/'.$item->sourceId:($item->entryType==='series'?'/series/'.$item->sourceId:'/series/'.$item->sourceId.'/temporadas/'.$item->seasonNumber); ?>
            <article class="schedule-card schedule-card--<?= e($state) ?>">
                <a class="schedule-card__poster" href="<?= e($path) ?>"><span><?= e(mb_strtoupper(mb_substr($item->title,0,1))) ?></span><?php if(isset($posterUrls[$item->id])):?><img src="<?= e($posterUrls[$item->id]) ?>" alt="" decoding="async"><?php endif;?></a>
                <div class="schedule-card__body"><p class="eyebrow"><?= e($item->entryType==='movie'?'Filme':'Série') ?></p><h3><a href="<?= e($path) ?>"><?= e($item->title) ?></a></h3>
                    <?php if($item->entryType==='episode'):?><p>T<?= e((string)$item->seasonNumber) ?>E<?= e((string)$item->episodeNumber) ?> · <?= e($item->episodeTitle ?? '') ?></p><?php elseif($item->year()!==null):?><p><?= e((string)$item->year()) ?></p><?php endif;?>
                    <strong><?= e($state==='today'?'Hoje':($state==='overdue'?'Atrasado':'Próximo')) ?> · <?= e((new DateTimeImmutable($item->scheduledOn))->format('d/m/Y')) ?></strong></div>
                <div class="schedule-card__actions"><form method="post" action="/agenda/<?= e((string)$item->id) ?>"><?= $csrf->field() ?><label for="scheduled-<?= e((string)$item->id) ?>">Reagendar</label><input id="scheduled-<?= e((string)$item->id) ?>" type="date" name="scheduled_on" value="<?= e(max($today,$item->scheduledOn)) ?>" min="<?= e($today) ?>" required><button class="button button--ghost" type="submit">Salvar</button></form><form method="post" action="/agenda/<?= e((string)$item->id) ?>/remover"><?= $csrf->field() ?><button class="button button--ghost" type="submit">Remover</button></form></div>
            </article>
        <?php endforeach; ?></div>
        <?php if($pageData->lastPage()>1):?><nav class="pagination" aria-label="Paginação"><?php for($p=1;$p<=$pageData->lastPage();$p++):?><a<?= $p===$pageData->page?' aria-current="page"':'' ?> href="/agenda?tipo=<?= e($typeFilter) ?>&amp;page=<?= e((string)$p) ?>"><?= e((string)$p) ?></a><?php endfor;?></nav><?php endif;?>
    <?php endif; ?>
</section>
