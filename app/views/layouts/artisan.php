<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Artisan Marketplace') ?></title>
    <link rel="stylesheet" href="/assets/css/global.css">
    <link rel="stylesheet" href="/assets/css/artisan-theme.css">
</head>
<body class="theme-artisan">
<?php View::partial('partials/nav'); ?>
<?php View::partial('partials/flash'); ?>
<main class="site-main artisan-main">
<?= $content ?>
</main>
<?php View::partial('partials/footer'); ?>
</body>
</html>
