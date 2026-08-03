<?php
/**
 * Shared page header for the public site. Each module page sets
 * $pageTitle and $theme ('main' | 'artisan' | 'business') before
 * requiring this file.
 */
$theme = $theme ?? 'main';
$pageTitle = $pageTitle ?? config_get('app_name');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/global.css">
    <?php if ($theme === 'artisan'): ?>
        <link rel="stylesheet" href="/assets/css/artisan-theme.css">
    <?php elseif ($theme === 'business'): ?>
        <link rel="stylesheet" href="/assets/css/business-theme.css">
    <?php endif; ?>
</head>
<body class="theme-<?= e($theme) ?>">
<?php require __DIR__ . '/nav.php'; ?>
<?php require __DIR__ . '/flash.php'; ?>
<main class="site-main <?= $theme !== 'main' ? e($theme) . '-main' : '' ?>">
