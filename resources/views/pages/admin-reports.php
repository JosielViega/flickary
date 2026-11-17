<?php
/** @var \App\Moderation\SocialReportPage $reports */
$date = static function (string $value): string { try { return (new DateTimeImmutable($value))->format('d/m/Y H:i'); } catch (Throwable) { return $value; } };
$filters = ['pending' => 'Pendentes', 'resolved' => 'Resolvidas', 'dismissed' => 'Arquivadas', 'all' => 'Todas'];
?>
<section class="admin-reports" aria-labelledby="admin-reports-title">
    <?php foreach (($messages['success'] ?? []) as $message): ?><p class="form-alert form-alert--success" role="status"><?= e((string) $message) ?></p><?php endforeach; ?>
    <header class="admin-reports__hero"><div><p class="eyebrow">Moderação Flickary</p><h1 id="admin-reports-title">Fila de denúncias</h1><p>Revisão baseada no snapshot preservado. Nenhuma decisão aplica sanção automática.</p></div><a class="button button--secondary" href="/perfil">Voltar ao perfil</a></header>
    <nav class="admin-filters" aria-label="Filtrar denúncias"><?php foreach ($filters as $key => $label): ?><a href="/admin/denuncias?status=<?= e($key) ?>"<?= $filter === $key ? ' aria-current="page" class="is-active"' : '' ?>><?= e($label) ?></a><?php endforeach; ?></nav>
    <?php if ($reports->items === []): ?><section class="reports-empty"><h2>Nenhuma denúncia neste filtro.</h2><p>A fila está em dia.</p></section>
    <?php else: ?><div class="admin-report-list" role="list"><?php foreach ($reports->items as $report): ?><a class="admin-report-row" role="listitem" href="/admin/denuncias/<?= $report->id ?>">
        <span class="admin-report-row__id">#<?= $report->id ?></span><span><small>Tipo</small><strong><?= e($report->targetType->label()) ?></strong></span><span><small>Motivo</small><strong><?= e($report->reason->label()) ?></strong></span><span><small>Reporter</small><strong>@<?= e($report->reporterUsername) ?></strong></span><span><small>Alvo</small><strong><?= e($report->targetDisplayName ?? 'Indisponível') ?></strong></span><time><?= e($date($report->createdAt)) ?></time><span class="report-status report-status--<?= e($report->status->value) ?>"><?= e($report->status->label()) ?></span>
    </a><?php endforeach; ?></div><?php endif; ?>
    <?php if ($reports->totalPages() > 1): ?><nav class="pagination" aria-label="Paginação da moderação"><?php if ($reports->page > 1): ?><a href="/admin/denuncias?status=<?= e($filter) ?>&amp;page=<?= $reports->page - 1 ?>">← Anterior</a><?php else: ?><span aria-disabled="true">← Anterior</span><?php endif; ?><strong>Página <?= $reports->page ?> de <?= $reports->totalPages() ?></strong><?php if ($reports->page < $reports->totalPages()): ?><a href="/admin/denuncias?status=<?= e($filter) ?>&amp;page=<?= $reports->page + 1 ?>">Próxima →</a><?php else: ?><span aria-disabled="true">Próxima →</span><?php endif; ?></nav><?php endif; ?>
</section>
