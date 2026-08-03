<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Admin') ?> · Admin Panel</title>
    <link rel="stylesheet" href="/assets/css/global.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="theme-admin">
<header class="admin-header">
    <a href="/admin" class="admin-brand">Marketplace Admin</a>
    <?php if (Auth::admin()): ?>
    <nav class="admin-nav">
        <a href="/admin/vendors">Vendor Approvals</a>
        <a href="/admin/category-requests">Category Approvals</a>
        <form method="post" action="/admin/logout" class="inline-form">
            <?= csrf_field() ?>
            <button type="submit" class="link-button">Logout</button>
        </form>
    </nav>
    <?php endif; ?>
</header>
<?php View::partial('partials/flash'); ?>
<main class="site-main admin-main">
<?= $content ?>
</main>
</body>
</html>
