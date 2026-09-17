<?php

declare(strict_types=1);

/** @var string $content */
/** @var string $title */
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#070D18">
    <title><?= e($title ?? 'Flickary') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="auth-page">
    <a class="skip-link" href="#conteudo-principal">Ir para o conteúdo</a>

    <div class="auth-scene" aria-hidden="true">
        <span class="auth-scene__poster auth-scene__poster--one"></span>
        <span class="auth-scene__poster auth-scene__poster--two"></span>
        <span class="auth-scene__poster auth-scene__poster--three"></span>
        <span class="auth-scene__beam"></span>
    </div>

    <main class="auth-shell" id="conteudo-principal">
        <?= $content ?>
    </main>
</body>
</html>
