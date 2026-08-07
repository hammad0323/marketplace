<?php
$pageTitle = $pageTitle ?? 'Platform';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= mp_e($pageTitle) ?> &middot; Platform Control Panel</title>
    <link rel="stylesheet" href="<?= mp_e(ROUTE_ASSETS) ?>css/global.css">
    <link rel="stylesheet" href="<?= mp_e(ROUTE_ASSETS) ?>css/admin.css">
</head>
<body class="theme-admin theme-platform">
<header class="admin-header" style="background:#12131c;">
    <a href="<?= mp_e(ROUTE_PLATFORM) ?>dashboard.php" class="admin-brand">⬢ Platform Control Panel</a>
    <?php if (mp_current_platform_admin()): ?>
    <nav class="admin-nav">
        <a href="<?= mp_e(ROUTE_PLATFORM) ?>dashboard.php">Dashboard</a>
        <a href="<?= mp_e(ROUTE_PLATFORM) ?>tenants.php">Tenants</a>
        <form method="post" action="<?= mp_e(ROUTE_PLATFORM) ?>logout.php" class="inline-form">
            <?= mp_csrf_field() ?>
            <button type="submit" class="link-button">Logout</button>
        </form>
    </nav>
    <?php endif; ?>
</header>
<?php
$flashSuccess = mp_flash('success');
$flashError = mp_flash('error');
?>
<?php if ($flashSuccess): ?>
    <div class="flash flash-success"><?= mp_e($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
    <div class="flash flash-error"><?= mp_e($flashError) ?></div>
<?php endif; ?>
<main class="site-main admin-main">
