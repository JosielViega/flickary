<?php
/** @var \App\Social\PublicProfile $user */
$cardInitial = mb_strtoupper(mb_substr($user->displayName !== '' ? $user->displayName : $user->username, 0, 1));
?>
<a class="user-card" href="/u/<?= rawurlencode($user->username) ?>">
    <span class="user-card__avatar">
        <span aria-hidden="true"><?= e($cardInitial !== '' ? $cardInitial : 'F') ?></span>
        <?php if ($user->avatarUrl !== null): ?>
            <img src="<?= e($user->avatarUrl) ?>" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.hidden=true">
        <?php endif; ?>
    </span>
    <span class="user-card__identity">
        <strong><?= e($user->displayName) ?></strong>
        <small>@<?= e($user->username) ?></small>
    </span>
    <span class="user-card__privacy"><?= $user->isPrivate ? 'Perfil privado' : 'Perfil público' ?></span>
</a>
