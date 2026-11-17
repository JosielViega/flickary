<?php
/** @var \App\Moderation\SocialReportPage $reports */
$date = static function (string $value): string { try { return (new DateTimeImmutable($value))->format('d/m/Y H:i'); } catch (Throwable) { return $value; } };
?>
<section class="reports-page" aria-labelledby="reports-title">
    <?php foreach (['success', 'info'] as $type): foreach (($messages[$type] ?? []) as $message): ?><p class="form-alert form-alert--success" role="status"><?= e((string) $message) ?></p><?php endforeach; endforeach; ?>
    <header class="reports-hero"><div><p class="eyebrow">Privacidade e segurança</p><h1 id="reports-title">Minhas denúncias</h1><p>Acompanhe os registros enviados por você. “Resolvida” indica que a revisão terminou, não que houve uma sanção.</p></div><a class="button button--secondary" href="/perfil">Voltar ao perfil</a></header>
    <?php if ($reports->items === []): ?>
        <section class="reports-empty"><span aria-hidden="true">✦</span><h2>Nenhuma denúncia enviada.</h2><p>Quando você sinalizar um perfil ou conteúdo, o andamento aparecerá aqui.</p></section>
    <?php else: ?>
        <ol class="report-cards"><?php foreach ($reports->items as $report): ?><li><article class="report-card">
            <header><span class="report-status report-status--<?= e($report->status->value) ?>"><?= e($report->status->label()) ?></span><time><?= e($date($report->createdAt)) ?></time></header>
            <p class="eyebrow"><?= e($report->targetType->label()) ?> · <?= e($report->reason->label()) ?></p>
            <h2><?= e($report->targetDisplayName ?? 'Usuário indisponível') ?></h2>
            <?php if ($report->targetUsername !== null): ?><p>@<?= e($report->targetUsername) ?></p><?php endif; ?>
        </article></li><?php endforeach; ?></ol>
    <?php endif; ?>
    <?php if ($reports->totalPages() > 1): ?><nav class="pagination" aria-label="Paginação das denúncias"><span><?= $reports->page > 1 ? '<a href="/perfil/denuncias?page=' . ($reports->page - 1) . '">← Anterior</a>' : '<span aria-disabled="true">← Anterior</span>' ?></span><strong>Página <?= $reports->page ?> de <?= $reports->totalPages() ?></strong><span><?= $reports->page < $reports->totalPages() ? '<a href="/perfil/denuncias?page=' . ($reports->page + 1) . '">Próxima →</a>' : '<span aria-disabled="true">Próxima →</span>' ?></span></nav><?php endif; ?>
</section>
