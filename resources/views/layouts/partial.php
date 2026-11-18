<?php

declare(strict_types=1);

/** @var string $content */
/** @var string $title */
/** @var null|string $currentRoute */
/** @var null|string $searchQuery */
/** @var null|\App\Core\PageMeta $pageMeta */

$messagesPresent = is_array($messages ?? null) && $messages !== [];
$cacheable = ($partialCacheable ?? true) === true && !$messagesPresent;
?>
<template data-flickary-head>
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
</template>
<div
    data-flickary-partial
    data-title="<?= e($title ?? 'Flickary') ?>"
    data-current-route="<?= e(is_string($currentRoute ?? null) ? $currentRoute : '') ?>"
    data-search-query="<?= e(is_string($searchQuery ?? null) ? $searchQuery : '') ?>"
    data-cacheable="<?= $cacheable ? '1' : '0' ?>"
>
    <div data-flickary-content><?= $content ?></div>
</div>
