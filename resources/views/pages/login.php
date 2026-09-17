<?php

declare(strict_types=1);

/** @var bool $googleEnabled */
/** @var string $googleClientId */
/** @var string $googleLoginUri */
/** @var array $messages */
?>
<section class="auth-panel" aria-labelledby="login-title">
    <a class="auth-brand" href="/" aria-label="Flickary — início">
        <span class="auth-brand__mark" aria-hidden="true">F</span>
        <span>
            <strong>Flickary</strong>
            <small>Viver histórias.</small>
        </span>
    </a>

    <div class="auth-panel__intro">
        <p class="eyebrow">Passado · Presente · Futuro</p>
        <h1 id="login-title">Sua próxima história começa aqui.</h1>
        <p>Entre com sua conta Google para guardar jornadas, descobertas e tudo o que ainda vem pela frente.</p>
    </div>

    <?php foreach (($messages['error'] ?? []) as $message): ?>
        <p class="form-alert form-alert--error" role="alert"><?= e((string) $message) ?></p>
    <?php endforeach; ?>

    <?php if ($googleEnabled): ?>
        <div class="google-signin" aria-label="Acesso com Google">
            <div id="g_id_onload"
                 data-client_id="<?= e($googleClientId) ?>"
                 data-login_uri="<?= e($googleLoginUri) ?>"
                 data-ux_mode="popup"
                 data-auto_prompt="false"></div>
            <div class="g_id_signin"
                 data-type="standard"
                 data-theme="filled_black"
                 data-size="large"
                 data-text="continue_with"
                 data-shape="rectangular"
                 data-logo_alignment="left"
                 data-width="320"
                 data-locale="pt-BR"></div>
        </div>
        <script src="https://accounts.google.com/gsi/client?hl=pt-BR" async defer></script>
    <?php else: ?>
        <div class="auth-unavailable" role="status">
            <strong>Acesso indisponível neste ambiente</strong>
            <span>O login com Google ainda precisa ser configurado pelo responsável da instalação.</span>
        </div>
    <?php endif; ?>

    <p class="auth-panel__privacy">O Flickary usa somente sua identidade Google verificada para encontrar ou criar sua conta. Nenhum token é armazenado.</p>
    <a class="auth-back" href="/">Voltar para a Home</a>
</section>
