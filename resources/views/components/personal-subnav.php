<?php

declare(strict_types=1);

/** @var string $personalSection */
$items = [
    'library' => ['/minha-lista', 'Minha Lista'],
    'favorites' => ['/favoritos', 'Favoritos'],
    'ratings' => ['/avaliacoes', 'Avaliações'],
    'history' => ['/historico', 'Histórico'],
    'statistics' => ['/estatisticas', 'Estatísticas'],
];
?>
<nav class="personal-subnav" aria-label="Sua área pessoal">
    <?php foreach ($items as $key => [$path, $label]): ?>
        <a<?= $personalSection === $key ? ' class="is-active" aria-current="page"' : '' ?> href="<?= e($path) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
