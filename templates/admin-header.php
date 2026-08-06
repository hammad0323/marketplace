<?php
$pageTitle = $pageTitle ?? 'Admin';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= mp_e($pageTitle) ?> &middot; Admin Panel</title>
    <link rel="stylesheet" href="<?= mp_e(ROUTE_ASSETS) ?>css/global.css">
    <link rel="stylesheet" href="<?= mp_e(ROUTE_ASSETS) ?>css/admin.css">
</head>
<body class="theme-admin">
<header class="admin-header">
    <a href="<?= mp_e(ROUTE_ADMIN) ?>dashboard.php" class="admin-brand">Marketplace Admin</a>
    <?php if (mp_current_admin()): ?>
    <nav class="admin-nav">
        <a href="<?= mp_e(ROUTE_ADMIN) ?>dashboard.php">Dashboard</a>
        <a href="<?= mp_e(ROUTE_ADMIN) ?>reports.php">Reports</a>
        <a href="<?= mp_e(ROUTE_ADMIN) ?>orders.php">Orders</a>
        <a href="<?= mp_e(ROUTE_ADMIN) ?>products.php">Products</a>
        <a href="<?= mp_e(ROUTE_ADMIN) ?>categories.php">Categories</a>
        <a href="<?= mp_e(ROUTE_ADMIN) ?>vendors.php">Vendor Approvals</a>
        <a href="<?= mp_e(ROUTE_ADMIN) ?>category-requests.php">Category Approvals</a>
        <a href="<?= mp_e(ROUTE_ADMIN) ?>customers.php">Customers</a>
        <a href="<?= mp_e(ROUTE_ADMIN) ?>banners.php">Banners</a>
        <a href="<?= mp_e(ROUTE_ADMIN) ?>settings.php">Settings</a>
        <a href="<?= mp_e(ROUTE_ADMIN) ?>admin-users.php">Admin Users</a>
        <a href="<?= mp_e(ROUTE_ADMIN) ?>activity-log.php">Activity Log</a>
        <form method="post" action="<?= mp_e(ROUTE_ADMIN) ?>logout.php" class="inline-form">
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
