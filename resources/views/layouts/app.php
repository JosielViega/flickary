<?php

declare(strict_types=1);

/** @var string $content */
/** @var string $title */
/** @var null|string $currentRoute */
/** @var null|\App\Core\Auth $auth */
/** @var null|\App\Core\Csrf $csrf */
/** @var null|\App\Core\PageMeta $pageMeta */

$homeIsActive = ($currentRoute ?? null) === 'home';
$profileIsActive = ($currentRoute ?? null) === 'profile';
$searchIsActive = ($currentRoute ?? null) === 'search';
$aboutIsActive = ($currentRoute ?? null) === 'about';
$libraryIsActive = ($currentRoute ?? null) === 'library';
$agendaIsActive = ($currentRoute ?? null) === 'agenda';
$statisticsIsActive = ($currentRoute ?? null) === 'statistics';
$socialIsActive = ($currentRoute ?? null) === 'social';
$profileBottomIsActive = $profileIsActive || $statisticsIsActive || $socialIsActive;
$searchQuery = is_string($searchQuery ?? null) ? $searchQuery : '';
$isAuthenticated = isset($auth) && $auth instanceof \App\Core\Auth && $auth->check();
$notificationUnreadCount = max(0, (int) ($notificationUnreadCount ?? 0));
$notificationBadge = $notificationUnreadCount > 99 ? '99+' : (string) $notificationUnreadCount;
$notificationUnreadLabel = $notificationUnreadCount === 1 ? '1 não lida' : $notificationUnreadCount . ' não lidas';
$messageUnreadCount = max(0, (int) ($messageUnreadCount ?? 0));
$messageBadge = $messageUnreadCount > 99 ? '99+' : (string) $messageUnreadCount;
$messageUnreadLabel = $messageUnreadCount === 1 ? '1 não lida' : $messageUnreadCount . ' não lidas';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0B1220">
    <title><?= e($title ?? 'Flickary') ?></title>
    <?php if (($pageMeta ?? null) instanceof \App\Core\PageMeta): ?>
        <meta data-flickary-page-head name="description" content="<?= e($pageMeta->description) ?>">
        <link data-flickary-page-head rel="canonical" href="<?= e($pageMeta->canonicalUrl) ?>">
        <meta data-flickary-page-head property="og:title" content="<?= e($pageMeta->title) ?>">
        <meta data-flickary-page-head property="og:type" content="<?= e($pageMeta->type) ?>">
        <meta data-flickary-page-head property="og:url" content="<?= e($pageMeta->canonicalUrl) ?>">
        <meta data-flickary-page-head property="og:description" content="<?= e($pageMeta->description) ?>">
        <meta data-flickary-page-head property="og:site_name" content="Flickary">
        <meta data-flickary-page-head property="og:locale" content="pt_BR">
        <meta data-flickary-page-head property="og:image" content="<?= e($pageMeta->imageUrl) ?>">
        <meta data-flickary-page-head property="og:image:alt" content="<?= e($pageMeta->imageAlt) ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css?v=32.0">
</head>
<body>
    <div class="navigation-progress" aria-hidden="true"><span></span></div>
    <a class="skip-link" href="#conteudo-principal">Ir para o conteúdo</a>

    <svg class="icon-sprite" aria-hidden="true" focusable="false">
        <symbol id="icon-home" viewBox="0 0 24 24">
            <path d="M3.5 10.5 12 3l8.5 7.5v9a1.5 1.5 0 0 1-1.5 1.5h-4.5v-6h-5v6H5a1.5 1.5 0 0 1-1.5-1.5z"/>
        </symbol>
        <symbol id="icon-calendar" viewBox="0 0 24 24">
            <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>
        </symbol>
        <symbol id="icon-search" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="7"/><path d="m16.25 16.25 4.25 4.25"/>
        </symbol>
        <symbol id="icon-bookmark" viewBox="0 0 24 24">
            <path d="M6 4.5A1.5 1.5 0 0 1 7.5 3h9A1.5 1.5 0 0 1 18 4.5V21l-6-3.75L6 21z"/>
        </symbol>
        <symbol id="icon-user" viewBox="0 0 24 24">
            <circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/>
        </symbol>
        <symbol id="icon-chart" viewBox="0 0 24 24">
            <path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>
        </symbol>
        <symbol id="icon-users" viewBox="0 0 24 24">
            <circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 5.5a3.5 3.5 0 0 1 0 6.8M17 15a5.5 5.5 0 0 1 4.5 5"/>
        </symbol>
        <symbol id="icon-bell" viewBox="0 0 24 24"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></symbol>
        <symbol id="icon-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></symbol>
    </svg>

    <div class="app-shell">
        <aside class="sidebar" aria-label="Navegação principal">
            <a class="brand brand--sidebar" href="/" aria-label="Flickary — início">
                <span class="brand__name">Flickary</span>
                <span class="brand__tagline">Viver histórias.</span>
            </a>

            <nav class="sidebar__nav">
                <a class="nav-item<?= $homeIsActive ? ' is-active' : '' ?>" href="/"<?= $homeIsActive ? ' aria-current="page"' : '' ?> data-nav-route="home">
                    <svg aria-hidden="true"><use href="#icon-home"/></svg>
                    <span>Início</span>
                </a>
                <?php if ($isAuthenticated): ?>
                    <a class="nav-item<?= $agendaIsActive ? ' is-active' : '' ?>" href="/agenda"<?= $agendaIsActive ? ' aria-current="page"' : '' ?> data-nav-route="agenda"><svg aria-hidden="true"><use href="#icon-calendar"/></svg><span>Agenda</span></a>
                <?php else: ?>
                    <span class="nav-item is-disabled" aria-disabled="true" title="Entre para acessar sua agenda"><svg aria-hidden="true"><use href="#icon-calendar"/></svg><span>Agenda</span></span>
                <?php endif; ?>
                <a class="nav-item<?= $searchIsActive ? ' is-active' : '' ?>" href="/buscar"<?= $searchIsActive ? ' aria-current="page"' : '' ?> data-nav-route="search">
                    <svg aria-hidden="true"><use href="#icon-search"/></svg>
                    <span>Buscar</span>
                </a>
                <?php if ($isAuthenticated): ?>
                    <a class="nav-item<?= $libraryIsActive ? ' is-active' : '' ?>" href="/minha-lista"<?= $libraryIsActive ? ' aria-current="page"' : '' ?> data-nav-route="library">
                        <svg aria-hidden="true"><use href="#icon-bookmark"/></svg><span>Minha Lista</span>
                    </a>
                <?php else: ?>
                    <span class="nav-item is-disabled" aria-disabled="true" title="Entre para acessar sua lista">
                        <svg aria-hidden="true"><use href="#icon-bookmark"/></svg><span>Minha Lista</span>
                    </span>
                <?php endif; ?>
                <?php if ($isAuthenticated): ?>
                    <a class="nav-item<?= $statisticsIsActive ? ' is-active' : '' ?>" href="/estatisticas"<?= $statisticsIsActive ? ' aria-current="page"' : '' ?> data-nav-route="statistics"><svg aria-hidden="true"><use href="#icon-chart"/></svg><span>Estatísticas</span></a>
                <?php else: ?>
                    <span class="nav-item is-disabled" aria-disabled="true" title="Entre para acessar suas estatísticas"><svg aria-hidden="true"><use href="#icon-chart"/></svg><span>Estatísticas</span></span>
                <?php endif; ?>
                <?php if ($isAuthenticated): ?>
                    <a class="nav-item<?= $profileIsActive ? ' is-active' : '' ?>" href="/perfil"<?= $profileIsActive ? ' aria-current="page"' : '' ?> data-nav-route="profile">
                        <svg aria-hidden="true"><use href="#icon-user"/></svg>
                        <span>Perfil</span>
                    </a>
                <?php else: ?>
                    <span class="nav-item is-disabled" aria-disabled="true" title="Entre para acessar seu perfil">
                        <svg aria-hidden="true"><use href="#icon-user"/></svg>
                        <span>Perfil</span>
                    </span>
                <?php endif; ?>
                <?php if ($isAuthenticated): ?>
                    <a class="nav-item<?= $socialIsActive ? ' is-active' : '' ?>" href="/social"<?= $socialIsActive ? ' aria-current="page"' : '' ?> data-nav-route="social">
                        <svg aria-hidden="true"><use href="#icon-users"/></svg><span>Social</span>
                    </a>
                <?php else: ?>
                    <span class="nav-item is-disabled" aria-disabled="true" title="Entre para acessar o Social">
                        <svg aria-hidden="true"><use href="#icon-users"/></svg><span>Social</span>
                    </span>
                <?php endif; ?>
            </nav>

            <a class="sidebar__about<?= $aboutIsActive ? ' is-active' : '' ?>" href="/sobre"<?= $aboutIsActive ? ' aria-current="page"' : '' ?> data-nav-route="about">Sobre</a>
            <p class="sidebar__signature">Passado · Presente · Futuro</p>
        </aside>

        <div class="app-frame">
            <header class="topbar">
                <a class="brand brand--mobile" href="/" aria-label="Flickary — início">
                    <span class="brand__name">Flickary</span>
                </a>

                <form class="topbar__search" method="get" action="/buscar" role="search" data-partial-get>
                    <svg aria-hidden="true"><use href="#icon-search"/></svg>
                    <label class="visually-hidden" for="topbar-search">Buscar filmes e séries</label>
                    <input id="topbar-search" name="q" type="search" value="<?= e($searchQuery) ?>" placeholder="Buscar filmes e séries..." minlength="2" maxlength="120" autocomplete="off">
                    <button type="submit" aria-label="Pesquisar">Buscar</button>
                </form>

                <div class="topbar__account">
                    <?php if ($isAuthenticated && isset($csrf) && $csrf instanceof \App\Core\Csrf): ?>
                        <a class="notification-action message-action" href="/mensagens" aria-label="Mensagens<?= $messageUnreadCount > 0 ? ', ' . e($messageUnreadLabel) : '' ?>"><svg aria-hidden="true"><use href="#icon-mail"/></svg><span class="notification-action__label">Mensagens</span><?php if($messageUnreadCount>0): ?><strong class="notification-badge"><?= e($messageBadge) ?></strong><?php endif; ?></a>
                        <a class="notification-action" href="/notificacoes" aria-label="Notificações<?= $notificationUnreadCount > 0 ? ', ' . e($notificationUnreadLabel) : '' ?>"><svg aria-hidden="true"><use href="#icon-bell"/></svg><span class="notification-action__label">Notificações</span><?php if($notificationUnreadCount>0): ?><strong class="notification-badge"><?= e($notificationBadge) ?></strong><?php endif; ?></a>
                        <form method="post" action="/logout">
                            <?= $csrf->field() ?>
                            <button class="account-action" type="submit">Sair</button>
                        </form>
                    <?php else: ?>
                        <a class="account-action" href="/login">Entrar</a>
                    <?php endif; ?>
                </div>

                <p class="topbar__signature">Mais que assistir. <strong>Viver histórias.</strong></p>
            </header>

            <main class="app-content" id="conteudo-principal" tabindex="-1">
                <?= $content ?>
            </main>
        </div>

        <nav class="bottom-nav" aria-label="Navegação principal">
            <a class="bottom-nav__item<?= $homeIsActive ? ' is-active' : '' ?>" href="/"<?= $homeIsActive ? ' aria-current="page"' : '' ?> data-nav-route="home">
                <svg aria-hidden="true"><use href="#icon-home"/></svg>
                <span>Início</span>
            </a>
            <?php if ($isAuthenticated): ?>
                <a class="bottom-nav__item<?= $agendaIsActive ? ' is-active' : '' ?>" href="/agenda"<?= $agendaIsActive ? ' aria-current="page"' : '' ?> data-nav-route="agenda"><svg aria-hidden="true"><use href="#icon-calendar"/></svg><span>Agenda</span></a>
            <?php else: ?>
                <span class="bottom-nav__item is-disabled" aria-disabled="true"><svg aria-hidden="true"><use href="#icon-calendar"/></svg><span>Agenda</span></span>
            <?php endif; ?>
            <a class="bottom-nav__item bottom-nav__item--search<?= $searchIsActive ? ' is-active' : '' ?>" href="/buscar"<?= $searchIsActive ? ' aria-current="page"' : '' ?> data-nav-route="search">
                <span class="bottom-nav__search-icon">
                    <svg aria-hidden="true"><use href="#icon-search"/></svg>
                </span>
                <span>Buscar</span>
            </a>
            <?php if ($isAuthenticated): ?>
                <a class="bottom-nav__item<?= $libraryIsActive ? ' is-active' : '' ?>" href="/minha-lista"<?= $libraryIsActive ? ' aria-current="page"' : '' ?> data-nav-route="library">
                    <svg aria-hidden="true"><use href="#icon-bookmark"/></svg><span>Minha Lista</span>
                </a>
            <?php else: ?>
                <span class="bottom-nav__item is-disabled" aria-disabled="true">
                    <svg aria-hidden="true"><use href="#icon-bookmark"/></svg><span>Minha Lista</span>
                </span>
            <?php endif; ?>
            <?php if ($isAuthenticated): ?>
                <a class="bottom-nav__item<?= $profileBottomIsActive ? ' is-active' : '' ?>" href="/perfil"<?= $profileBottomIsActive ? ' aria-current="page"' : '' ?> data-nav-route="profile statistics social">
                    <svg aria-hidden="true"><use href="#icon-user"/></svg>
                    <span>Perfil</span>
                </a>
            <?php else: ?>
                <span class="bottom-nav__item is-disabled" aria-disabled="true">
                    <svg aria-hidden="true"><use href="#icon-user"/></svg>
                    <span>Perfil</span>
                </span>
            <?php endif; ?>
        </nav>
    </div>
    <script src="/assets/js/share.js?v=32.0" defer></script>
    <script src="/assets/js/app-navigation.js?v=32.0" defer></script>
</body>
</html>
