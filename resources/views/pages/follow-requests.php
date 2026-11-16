<?php
/** @var \App\Social\FollowRequestPage $requests */
?>
<section class="follow-requests" aria-labelledby="follow-requests-title">
    <?php foreach (($messages['success'] ?? []) as $message): ?>
        <p class="form-alert form-alert--success" role="status"><?= e((string) $message) ?></p>
    <?php endforeach; ?>
    <header class="follow-requests__header">
        <div><p class="eyebrow">Sua comunidade</p><h1 id="follow-requests-title">Solicitações de seguidores</h1></div>
        <a class="button button--secondary" href="/perfil">Voltar ao perfil</a>
    </header>
    <?php if ($requests->items === []): ?>
        <div class="follow-requests__empty"><strong>Nenhuma solicitação pendente.</strong><p>Quando alguém pedir para acompanhar seu perfil privado, aparecerá aqui.</p></div>
    <?php else: ?>
        <div class="follow-requests__list">
            <?php foreach ($requests->items as $followRequest):
                $requester = $followRequest->requester;
                $initial = mb_strtoupper(mb_substr($requester->displayName ?: $requester->username, 0, 1));
                $requestedAt = $followRequest->createdAt;
                try { $requestedAt = (new DateTimeImmutable($requestedAt))->format('d/m/Y H:i'); } catch (Throwable) {}
            ?>
                <article class="follow-request-card">
                    <a class="follow-request-card__identity" href="/u/<?= rawurlencode($requester->username) ?>">
                        <span class="follow-request-card__avatar"><span aria-hidden="true"><?= e($initial ?: 'F') ?></span>
                            <?php if ($requester->avatarUrl !== null): ?><img src="<?= e($requester->avatarUrl) ?>" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.hidden=true"><?php endif; ?>
                        </span>
                        <span><strong><?= e($requester->displayName) ?></strong><small>@<?= e($requester->username) ?></small><time datetime="<?= e($followRequest->createdAt) ?>">Solicitado em <?= e($requestedAt) ?></time></span>
                    </a>
                    <div class="follow-request-card__actions">
                        <form method="post" action="/perfil/solicitacoes/<?= rawurlencode($requester->username) ?>/aceitar"><?= $csrf->field() ?><button class="button button--primary" type="submit">Aceitar</button></form>
                        <form method="post" action="/perfil/solicitacoes/<?= rawurlencode($requester->username) ?>/recusar"><?= $csrf->field() ?><button class="button button--secondary" type="submit">Recusar</button></form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if ($requests->totalPages() > 1): ?>
            <nav class="pagination" aria-label="Páginas de solicitações">
                <?php if ($requests->page > 1): ?><a href="?page=<?= $requests->page - 1 ?>">Anterior</a><?php endif; ?>
                <span>Página <?= $requests->page ?> de <?= $requests->totalPages() ?></span>
                <?php if ($requests->page < $requests->totalPages()): ?><a href="?page=<?= $requests->page + 1 ?>">Próxima</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
