<?php

declare(strict_types=1);

use App\Authentication\GoogleIdentity;

/** @var GoogleIdentity $identity */
/** @var string $username */
/** @var null|string $error */
/** @var string $csrfField */
?>
<section class="auth-panel auth-panel--onboarding" aria-labelledby="onboarding-title">
    <a class="auth-brand" href="/" aria-label="Flickary — início">
        <span class="auth-brand__mark" aria-hidden="true">F</span>
        <span>
            <strong>Flickary</strong>
            <small>Viver histórias.</small>
        </span>
    </a>

    <div class="auth-panel__intro">
        <p class="eyebrow">Só falta um detalhe</p>
        <h1 id="onboarding-title">Como você quer ser encontrado?</h1>
        <p>Escolha seu username Flickary. Ele será único e poderá representar você nas próximas experiências do produto.</p>
    </div>

    <div class="verified-identity">
        <?php if ($identity->avatarUrl !== null): ?>
            <img src="<?= e($identity->avatarUrl) ?>" alt="" referrerpolicy="no-referrer">
        <?php else: ?>
            <span class="verified-identity__fallback" aria-hidden="true">G</span>
        <?php endif; ?>
        <span>
            <strong><?= e($identity->displayName ?? 'Conta Google verificada') ?></strong>
            <?php if ($identity->email !== null): ?>
                <small><?= e($identity->email) ?></small>
            <?php else: ?>
                <small>Identidade Google verificada</small>
            <?php endif; ?>
        </span>
        <em>Verificada</em>
    </div>

    <?php if ($error !== null): ?>
        <p class="form-alert form-alert--error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form class="onboarding-form" method="post" action="/onboarding/username" novalidate>
        <?= $csrfField ?>
        <label for="username">Username</label>
        <div class="username-field">
            <span aria-hidden="true">@</span>
            <input id="username"
                   name="username"
                   type="text"
                   value="<?= e($username) ?>"
                   minlength="3"
                   maxlength="30"
                   autocomplete="username"
                   autocapitalize="none"
                   spellcheck="false"
                   required
                   aria-describedby="username-hint"<?= $error !== null ? ' aria-invalid="true"' : '' ?>>
        </div>
        <p id="username-hint">3–30 caracteres. Use letras, números, ponto ou underscore. Comece e termine com letra ou número.</p>
        <button class="button button--primary button--wide" type="submit">Criar minha conta</button>
    </form>
</section>
