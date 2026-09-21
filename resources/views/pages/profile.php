<?php

declare(strict_types=1);

/** @var array $profile */
/** @var array $form */
/** @var array $errors */
/** @var array $messages */
/** @var array $connectedProviders */
/** @var bool $facebookEnabled */

$displayName = (string) $profile['display_name'];
$username = (string) $profile['username'];
$initialSource = $displayName !== '' ? $displayName : $username;
$initial = function_exists('mb_substr') ? mb_substr($initialSource, 0, 1) : substr($initialSource, 0, 1);
$initial = function_exists('mb_strtoupper') ? mb_strtoupper($initial) : strtoupper($initial);
$avatarUrl = $profile['avatar_url'] ?? null;
$coverUrl = $profile['cover_url'] ?? null;
$bio = $profile['bio'] ?? null;
$isPrivate = (bool) ($profile['is_private'] ?? false);
?>
<section class="profile-page" aria-labelledby="profile-title">
    <?php foreach (($messages['success'] ?? []) as $message): ?>
        <p class="form-alert form-alert--success profile-feedback" role="status"><?= e((string) $message) ?></p>
    <?php endforeach; ?>
    <?php foreach (($messages['error'] ?? []) as $message): ?>
        <p class="form-alert form-alert--error profile-feedback" role="alert"><?= e((string) $message) ?></p>
    <?php endforeach; ?>

    <article class="profile-hero">
        <div class="profile-cover" aria-label="Capa do perfil">
            <div class="profile-cover__fallback" aria-hidden="true">
                <span class="profile-cover__beam profile-cover__beam--one"></span>
                <span class="profile-cover__beam profile-cover__beam--two"></span>
                <span class="profile-cover__frame profile-cover__frame--back"></span>
                <span class="profile-cover__frame profile-cover__frame--front"></span>
                <span class="profile-cover__signature">Passado · Presente · Futuro</span>
            </div>
            <?php if (is_string($coverUrl)): ?>
                <img class="profile-cover__image"
                     src="<?= e($coverUrl) ?>"
                     alt=""
                     loading="eager"
                     decoding="async"
                     referrerpolicy="no-referrer"
                     onerror="this.hidden=true">
            <?php endif; ?>
        </div>

        <div class="profile-identity">
            <div class="profile-avatar" aria-label="Avatar de <?= e($displayName) ?>">
                <span class="profile-avatar__fallback" aria-hidden="true"><?= e($initial !== '' ? $initial : 'F') ?></span>
                <?php if (is_string($avatarUrl)): ?>
                    <img src="<?= e($avatarUrl) ?>"
                         alt="Avatar de <?= e($displayName) ?>"
                         loading="eager"
                         decoding="async"
                         referrerpolicy="no-referrer"
                         onerror="this.hidden=true">
                <?php endif; ?>
            </div>

            <div class="profile-identity__copy">
                <p class="eyebrow">Sua identidade Flickary</p>
                <h1 id="profile-title"><?= e($displayName) ?></h1>
                <p class="profile-handle">@<?= e($username) ?></p>
                <?php if (is_string($bio) && $bio !== ''): ?>
                    <p class="profile-bio"><?= e($bio) ?></p>
                <?php else: ?>
                    <p class="profile-bio profile-bio--empty">Conte um pouco sobre as histórias que fazem parte de você.</p>
                <?php endif; ?>
            </div>

            <dl class="profile-meta" aria-label="Informações do perfil">
                <div>
                    <dt>Visibilidade</dt>
                    <dd class="profile-status<?= $isPrivate ? ' profile-status--private' : '' ?>">
                        <span aria-hidden="true"></span>
                        <?= $isPrivate ? 'Perfil privado' : 'Perfil público' ?>
                    </dd>
                </div>
                <?php if (is_string($profile['created_at'] ?? null)): ?>
                    <div>
                        <dt>Na jornada desde</dt>
                        <dd><?= e($profile['created_at']) ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
        </div>
    </article>

    <div class="profile-grid">
        <a class="profile-statistics-link" href="/estatisticas">
            <span><span class="eyebrow">Sua jornada</span><strong>Ver minhas estatísticas</strong></span>
            <span aria-hidden="true">→</span>
        </a>
        <section class="profile-story" aria-labelledby="profile-story-title">
            <p class="eyebrow">Identidade pessoal</p>
            <h2 id="profile-story-title">Seu espaço entre histórias.</h2>
            <p>Este é o começo do seu perfil no Flickary. Sua identidade acompanha o que você viveu, vive e ainda quer descobrir.</p>
            <div class="profile-story__note">
                <strong><?= $isPrivate ? 'Preferência privada ativada' : 'Preferência pública ativada' ?></strong>
                <span>Essa escolha fica guardada para os futuros recursos públicos e sociais do produto.</span>
            </div>
        </section>

        <section class="profile-editor" aria-labelledby="profile-editor-title">
            <header class="profile-editor__header">
                <div>
                    <p class="eyebrow">Editar perfil</p>
                    <h2 id="profile-editor-title">Como você quer aparecer?</h2>
                </div>
                <span class="profile-editor__mark" aria-hidden="true">✦</span>
            </header>

            <?php if (isset($errors['form'])): ?>
                <p class="form-alert form-alert--error" role="alert"><?= e((string) $errors['form']) ?></p>
            <?php endif; ?>

            <form class="profile-form" method="post" action="/perfil" novalidate>
                <?= $csrf->field() ?>

                <div class="profile-field">
                    <label for="display_name">Nome de exibição</label>
                    <input id="display_name"
                           name="display_name"
                           type="text"
                           value="<?= e((string) ($form['display_name'] ?? '')) ?>"
                           maxlength="100"
                           required
                           autocomplete="name"
                           aria-describedby="display-name-hint<?= isset($errors['display_name']) ? ' display-name-error' : '' ?>"
                           <?= isset($errors['display_name']) ? 'aria-invalid="true"' : '' ?>>
                    <p id="display-name-hint">De 1 a 100 caracteres. Unicode e espaços são bem-vindos.</p>
                    <?php if (isset($errors['display_name'])): ?>
                        <p class="profile-field__error" id="display-name-error" role="alert"><?= e((string) $errors['display_name']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="profile-field">
                    <div class="profile-field__label-row">
                        <label for="bio">Bio</label>
                        <span>Até 500 caracteres</span>
                    </div>
                    <textarea id="bio"
                              name="bio"
                              rows="6"
                              maxlength="500"
                              aria-describedby="bio-hint<?= isset($errors['bio']) ? ' bio-error' : '' ?>"
                              <?= isset($errors['bio']) ? 'aria-invalid="true"' : '' ?>><?= e((string) ($form['bio'] ?? '')) ?></textarea>
                    <p id="bio-hint">Texto simples. HTML não será interpretado.</p>
                    <?php if (isset($errors['bio'])): ?>
                        <p class="profile-field__error" id="bio-error" role="alert"><?= e((string) $errors['bio']) ?></p>
                    <?php endif; ?>
                </div>

                <label class="privacy-toggle" for="is_private">
                    <input id="is_private"
                           name="is_private"
                           type="checkbox"
                           value="1"
                           <?= (bool) ($form['is_private'] ?? false) ? 'checked' : '' ?>>
                    <span class="privacy-toggle__control" aria-hidden="true"><span></span></span>
                    <span>
                        <strong>Perfil privado</strong>
                        <small>Salva sua preferência para os futuros recursos sociais.</small>
                    </span>
                </label>

                <button class="button button--primary button--wide" type="submit">Salvar alterações</button>
                <p class="profile-form__scope">Username, e-mail, avatar e capa não são alterados nesta etapa.</p>
            </form>
        </section>

        <section class="profile-connections" aria-labelledby="profile-connections-title">
            <div>
                <p class="eyebrow">Contas conectadas</p>
                <h2 id="profile-connections-title">Seus caminhos de acesso.</h2>
                <p>Conecte provedores com segurança para acessar a mesma identidade Flickary.</p>
            </div>
            <ul class="connection-list">
                <li>
                    <span class="connection-provider"><b aria-hidden="true">G</b> Google</span>
                    <strong class="connection-state<?= in_array('google', $connectedProviders, true) ? ' is-connected' : '' ?>">
                        <?= in_array('google', $connectedProviders, true) ? 'Conectada' : 'Não conectada' ?>
                    </strong>
                </li>
                <li>
                    <span class="connection-provider"><b aria-hidden="true">f</b> Facebook</span>
                    <?php if (in_array('facebook', $connectedProviders, true)): ?>
                        <strong class="connection-state is-connected">Conectada</strong>
                    <?php elseif ($facebookEnabled): ?>
                        <form method="post" action="/perfil/conexoes/facebook">
                            <?= $csrf->field() ?>
                            <button class="connection-action" type="submit">Conectar</button>
                        </form>
                    <?php else: ?>
                        <strong class="connection-state">Indisponível</strong>
                    <?php endif; ?>
                </li>
            </ul>
            <p class="profile-connections__privacy">Mostramos apenas os provedores conectados. Identificadores externos e tokens nunca são exibidos.</p>
        </section>
    </div>
</section>
