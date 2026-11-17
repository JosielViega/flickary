<?php

declare(strict_types=1);

/** @var \App\Notifications\SocialNotificationPage $notifications */
/** @var int $unreadCount */
$date=static function(string$value):string{try{return(new DateTimeImmutable($value))->format('d/m/Y H:i');}catch(Throwable){return$value;}};
$avatar=static function(?string$value):?string{if($value===null||strlen($value)>2048||filter_var($value,FILTER_VALIDATE_URL)===false){return null;}return strtolower((string)parse_url($value,PHP_URL_SCHEME))==='https'?$value:null;};
$initial=static function(string$value):string{$letter=function_exists('mb_substr')?mb_substr($value,0,1):substr($value,0,1);return function_exists('mb_strtoupper')?mb_strtoupper($letter):strtoupper($letter);};
$copy=static function(\App\Notifications\SocialNotification $item):string{return match($item->type){
    \App\Notifications\SocialNotificationType::FollowRequest=>'solicitou seguir você.',
    \App\Notifications\SocialNotificationType::NewFollower=>'começou a seguir você.',
    \App\Notifications\SocialNotificationType::FollowAccepted=>'aceitou sua solicitação para seguir.',
    \App\Notifications\SocialNotificationType::ActivityLike=>'curtiu sua atividade.',
    \App\Notifications\SocialNotificationType::ActivityComment=>'comentou na sua atividade.',
};};
?>
<section class="notifications-page" aria-labelledby="notifications-title">
    <header class="notifications-hero">
        <div><p class="eyebrow">Sua comunidade</p><h1 id="notifications-title">Notificações</h1><p>Acompanhe as interações da sua comunidade no Flickary.</p></div>
        <div class="notifications-hero__status"><strong><?= e((string)$unreadCount) ?></strong><span><?= $unreadCount===1?'não lida':'não lidas' ?></span></div>
        <?php if($unreadCount>0): ?><form method="post" action="/notificacoes/ler-todas"><?= $csrf->field() ?><button class="button button--secondary" type="submit">Marcar todas como lidas</button></form><?php endif; ?>
    </header>

    <?php if($notifications->items===[]): ?>
        <section class="notifications-empty"><span aria-hidden="true">✦</span><h2>Você ainda não tem notificações.</h2><p>Novas interações sociais aparecerão aqui.</p></section>
    <?php else: ?>
        <ol class="notification-list">
            <?php foreach($notifications->items as$item):$actorAvatar=$avatar($item->actorAvatarUrl);$unread=$item->readAt===null; ?>
                <li><article class="notification-item<?= $unread?' is-unread':'' ?>">
                    <a class="notification-item__avatar" href="/u/<?= rawurlencode($item->actorUsername) ?>" aria-label="Perfil de <?= e($item->actorDisplayName) ?>"><span aria-hidden="true"><?= e($initial($item->actorDisplayName!==''?$item->actorDisplayName:$item->actorUsername)) ?></span><?php if($actorAvatar!==null): ?><img src="<?= e($actorAvatar) ?>" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.hidden=true"><?php endif; ?></a>
                    <div class="notification-item__copy"><p><strong><?= e($item->actorDisplayName) ?></strong> <span>(@<?= e($item->actorUsername) ?>)</span> <?= e($copy($item)) ?></p><time><?= e($date($item->createdAt)) ?></time><?php if($unread): ?><span class="notification-item__unread">Não lida</span><?php endif; ?></div>
                    <form method="post" action="/notificacoes/<?= e((string)$item->id) ?>/abrir"><?= $csrf->field() ?><button class="button button--secondary" type="submit">Abrir notificação</button></form>
                </article></li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>

    <?php if($notifications->totalPages()>1): ?><nav class="pagination" aria-label="Paginação das notificações"><?= $notifications->page>1?'<a href="/notificacoes?page='.($notifications->page-1).'">← Anterior</a>':'<span aria-disabled="true">← Anterior</span>' ?><strong>Página <?= $notifications->page ?> de <?= $notifications->totalPages() ?></strong><?= $notifications->page<$notifications->totalPages()?'<a href="/notificacoes?page='.($notifications->page+1).'">Próxima →</a>':'<span aria-disabled="true">Próxima →</span>' ?></nav><?php endif; ?>
</section>
