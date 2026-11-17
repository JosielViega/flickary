<?php
/** @var \App\Moderation\ReportTarget $target */
$initial = mb_strtoupper(mb_substr($target->displayName !== '' ? $target->displayName : $target->username, 0, 1));
?>
<section class="report-page" aria-labelledby="report-title">
    <a class="report-page__back" href="/perfil/denuncias">← Minhas denúncias</a>
    <div class="report-layout">
        <header class="report-hero">
            <p class="eyebrow">Segurança da comunidade</p>
            <h1 id="report-title">Enviar denúncia</h1>
            <p>Conte o que aconteceu. A equipe analisará o registro e o contexto preservado neste momento.</p>
            <div class="report-target">
                <span class="report-target__avatar"><b aria-hidden="true"><?= e($initial) ?></b><?php if ($target->avatarUrl !== null): ?><img src="<?= e($target->avatarUrl) ?>" alt="" referrerpolicy="no-referrer" onerror="this.hidden=true"><?php endif; ?></span>
                <span><small><?= e($target->type->label()) ?></small><strong><?= e($target->displayName) ?></strong><span>@<?= e($target->username) ?></span></span>
            </div>
            <p class="report-hero__privacy">A pessoa denunciada não será notificada. Enviar uma denúncia não bloqueia nem silencia automaticamente.</p>
        </header>
        <form class="report-form" method="post" action="<?= e($action) ?>" novalidate>
            <?= $csrf->field() ?>
            <?php if (isset($errors['form'])): ?><p class="form-alert form-alert--error" role="alert"><?= e((string) $errors['form']) ?></p><?php endif; ?>
            <fieldset<?= isset($errors['reason']) ? ' aria-describedby="report-reason-error"' : '' ?>>
                <legend>Qual é o motivo?</legend>
                <?php foreach ($reasons as $reason): ?>
                    <label class="report-reason"><input type="radio" name="reason" value="<?= e($reason->value) ?>"<?= $selectedReason === $reason->value ? ' checked' : '' ?>><span><strong><?= e($reason->label()) ?></strong></span></label>
                <?php endforeach; ?>
            </fieldset>
            <?php if (isset($errors['reason'])): ?><p class="profile-field__error" id="report-reason-error" role="alert"><?= e((string) $errors['reason']) ?></p><?php endif; ?>
            <div class="profile-field">
                <label for="report-details">Detalhes <span>(opcional, exceto para Outro)</span></label>
                <textarea id="report-details" name="details" rows="7" maxlength="1000" aria-describedby="report-details-hint<?= isset($errors['details']) ? ' report-details-error' : '' ?>"<?= isset($errors['details']) ? ' aria-invalid="true"' : '' ?>><?= e($details) ?></textarea>
                <p id="report-details-hint">Texto simples, até 1000 caracteres. Para “Outro”, escreva ao menos 10 caracteres.</p>
                <?php if (isset($errors['details'])): ?><p class="profile-field__error" id="report-details-error" role="alert"><?= e((string) $errors['details']) ?></p><?php endif; ?>
            </div>
            <div class="report-form__actions"><button class="button button--primary" type="submit">Enviar denúncia</button><a class="button button--secondary" href="/perfil/denuncias">Cancelar</a></div>
        </form>
    </div>
</section>
