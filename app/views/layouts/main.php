<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? config_get('app_name')) ?></title>
    <link rel="stylesheet" href="/assets/css/global.css">
</head>
<body class="theme-main">
<?php View::partial('partials/nav'); ?>
<?php View::partial('partials/flash'); ?>
<main class="site-main">
<?= $content ?>
</main>
<?php View::partial('partials/footer'); ?>
</body>
</html>
