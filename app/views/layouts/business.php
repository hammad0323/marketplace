<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Business Shops') ?></title>
    <link rel="stylesheet" href="/assets/css/global.css">
    <link rel="stylesheet" href="/assets/css/business-theme.css">
</head>
<body class="theme-business">
<?php View::partial('partials/nav'); ?>
<?php View::partial('partials/flash'); ?>
<main class="site-main business-main">
<?= $content ?>
</main>
<?php View::partial('partials/footer'); ?>
</body>
</html>
