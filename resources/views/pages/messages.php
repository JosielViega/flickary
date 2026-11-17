<?php
/** @var \App\Messaging\DirectConversationPage $conversations */
$date = static fn(string $value): string => (new DateTimeImmutable($value))->format('d/m/Y H:i');
$preview = static function(string $value): string { $plain=preg_replace('/\s+/u',' ',trim($value))??'';return mb_strlen($plain)>120?mb_substr($plain,0,117).'…':$plain; };
$initial = static fn(string $value): string => mb_strtoupper(mb_substr($value,0,1));
$safeAvatar = static fn(?string $value): ?string => is_string($value)
    && mb_strlen($value) <= 2048
    && filter_var($value, FILTER_VALIDATE_URL) !== false
    && strtolower((string) parse_url($value, PHP_URL_SCHEME)) === 'https' ? $value : null;
?>
<section class="messages-page" aria-labelledby="messages-title">
    <header class="messages-hero"><div><p class="eyebrow">Conversas privadas</p><h1 id="messages-title">Mensagens</h1><p>Converse com pessoas que também escolheram seguir você.</p></div><a class="button button--secondary" href="/usuarios">Encontrar pessoas</a></header>
    <?php if($conversations->items===[]): ?>
        <section class="messages-empty"><span aria-hidden="true">✦</span><h2>Você ainda não tem conversas.</h2><p>Quando o follow for mútuo, você poderá iniciar uma conversa pelo perfil da pessoa.</p><a class="button button--primary" href="/usuarios">Encontrar pessoas</a></section>
    <?php else: ?>
        <ol class="conversation-list">
        <?php foreach($conversations->items as$item):$unread=$item->unreadCount>0;$avatar=$safeAvatar($item->otherAvatarUrl); ?>
            <li><article class="conversation-card<?= $unread?' is-unread':'' ?>">
                <a class="conversation-card__avatar" href="/u/<?= rawurlencode($item->otherUsername) ?>"><span aria-hidden="true"><?= e($initial($item->otherDisplayName)) ?></span><?php if($avatar!==null):?><img src="<?= e($avatar) ?>" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.hidden=true"><?php endif;?></a>
                <div class="conversation-card__copy"><h2><?= e($item->otherDisplayName) ?></h2><p>@<?= e($item->otherUsername) ?></p><p class="conversation-card__preview"><strong><?= $item->latestSenderUserId===$currentUserId?'Você: ':'' ?></strong><?= e($preview($item->latestMessage)) ?></p><time><?= e($date($item->latestMessageAt)) ?></time><?php if(!$item->canSendNow):?><span class="conversation-card__readonly">Somente leitura</span><?php endif;?></div>
                <div class="conversation-card__action"><?php if($unread):?><span class="conversation-card__unread"><?= e((string)$item->unreadCount) ?> não lida<?= $item->unreadCount===1?'':'s' ?></span><form method="post" action="/mensagens/com/<?= rawurlencode($item->otherUsername) ?>/abrir"><?= $csrf->field() ?><button class="button button--primary" type="submit">Abrir e marcar como lida</button></form><?php else:?><a class="button button--secondary" href="/mensagens/com/<?= rawurlencode($item->otherUsername) ?>">Ver conversa</a><?php endif;?></div>
            </article></li>
        <?php endforeach; ?>
        </ol>
    <?php endif; ?>
    <?php if($conversations->totalPages()>1):?><nav class="pagination" aria-label="Paginação das conversas"><?php if($conversations->page>1):?><a href="/mensagens?page=<?= $conversations->page-1 ?>">← Anterior</a><?php else:?><span aria-disabled="true">← Anterior</span><?php endif;?><strong>Página <?= $conversations->page ?> de <?= $conversations->totalPages() ?></strong><?php if($conversations->page<$conversations->totalPages()):?><a href="/mensagens?page=<?= $conversations->page+1 ?>">Próxima →</a><?php else:?><span aria-disabled="true">Próxima →</span><?php endif;?></nav><?php endif;?>
</section>
