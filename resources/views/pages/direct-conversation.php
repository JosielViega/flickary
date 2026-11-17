<?php
/** @var \App\Social\PublicProfile $other */
/** @var ?\App\Messaging\DirectMessagePage $messagesPage */
$initial=mb_strtoupper(mb_substr($other->displayName!==''?$other->displayName:$other->username,0,1));
$date=static fn(string$value):string=>(new DateTimeImmutable($value))->format('d/m/Y H:i');
?>
<section class="direct-conversation" aria-labelledby="conversation-title">
    <a class="direct-conversation__back" href="/mensagens">← Voltar às mensagens</a>
    <header class="direct-conversation__header"><div class="direct-conversation__avatar"><span aria-hidden="true"><?= e($initial) ?></span><?php if($other->avatarUrl!==null):?><img src="<?= e($other->avatarUrl) ?>" alt="" referrerpolicy="no-referrer" onerror="this.hidden=true"><?php endif;?></div><div><p class="eyebrow">Conversa privada</p><h1 id="conversation-title"><?= e($other->displayName) ?></h1><p>@<?= e($other->username) ?></p></div><?php if(!($pairBlocked??false)):?><a class="button button--secondary" href="/u/<?= rawurlencode($other->username) ?>">Ver perfil</a><?php endif;?></header>
    <?php if(!$canSend):?><p class="direct-conversation__readonly" role="status"><strong>Conversa somente leitura.</strong> Esta conversa está somente leitura.</p><?php endif;?>
    <?php if($unreadCount>0):?><form class="direct-conversation__read" method="post" action="/mensagens/com/<?= rawurlencode($other->username) ?>/ler"><?= $csrf->field() ?><button class="button button--secondary" type="submit">Marcar como lidas</button></form><?php endif;?>
    <section class="message-thread" aria-label="Histórico da conversa">
        <?php if(!$conversationExists||$messagesPage===null||$messagesPage->items===[]):?><div class="message-thread__empty"><p>Esta conversa ainda não tem mensagens.</p></div><?php else:?>
            <?php if($messagesPage->page<$messagesPage->totalPages()):?><a class="message-thread__older" href="/mensagens/com/<?= rawurlencode($other->username) ?>?page=<?= $messagesPage->page+1 ?>">Mensagens anteriores</a><?php endif;?>
            <ol><?php foreach($messagesPage->items as$message):$mine=$message->senderUserId===$currentUserId;?><li class="message-bubble<?= $mine?' is-mine':' is-received' ?>"><span class="visually-hidden"><?= $mine?'Sua mensagem':'Mensagem recebida' ?></span><p><?= e($message->body) ?></p><footer><time><?= e($date($message->createdAt)) ?></time><?php if(!$mine):?><a href="/mensagens/<?= $message->id ?>/reportar">Reportar</a><?php endif;?></footer></li><?php endforeach;?></ol>
        <?php endif;?>
    </section>
    <?php if($canSend):?><section class="message-composer" aria-labelledby="message-composer-title"><h2 id="message-composer-title">Enviar mensagem</h2><?php if($messageError!==null):?><p class="form-alert form-alert--error" id="message-body-error" role="alert"><?= e($messageError) ?></p><?php endif;?><form method="post" action="/mensagens/com/<?= rawurlencode($other->username) ?>"><?= $csrf->field() ?><label for="message-body">Mensagem</label><textarea id="message-body" name="body" maxlength="2000" required<?= $messageError!==null?' aria-invalid="true" aria-describedby="message-body-error"':'' ?>><?= e($messageBody) ?></textarea><div><small>Texto simples · até 2000 caracteres</small><button class="button button--primary" type="submit">Enviar mensagem</button></div></form></section><?php endif;?>
</section>
