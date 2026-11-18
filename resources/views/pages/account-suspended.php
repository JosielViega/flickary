<?php
$date = static function (?string $value): ?string {
    if ($value === null) { return null; }
    try { return (new DateTimeImmutable($value))->format('d/m/Y \à\s H:i'); } catch (Throwable) { return null; }
};
$availableAt = $date($suspendedUntil);
?>
<section class="auth-panel suspension-panel" aria-labelledby="suspension-title">
    <a class="auth-brand" href="/" aria-label="Flickary — início"><span class="auth-brand__mark" aria-hidden="true">F</span><span><strong>Flickary</strong><small>Viver histórias.</small></span></a>
    <div class="auth-panel__intro"><p class="eyebrow">Acesso à conta</p><h1 id="suspension-title">Sua conta está temporariamente suspensa.</h1>
    <?php if ($availableAt !== null): ?><p>Acesso disponível novamente em <strong><?= e($availableAt) ?></strong>.</p><?php else: ?><p>Não há uma suspensão ativa associada a esta sessão.</p><?php endif; ?></div>
    <p class="auth-panel__privacy">Se precisar de ajuda, entre em contato com o suporte do Flickary. Detalhes internos de moderação permanecem privados.</p>
    <a class="button button--primary" href="/login">Voltar ao login</a>
</section>
