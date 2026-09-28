<?php
$adminPage = $adminPage ?? '';
$counts = [
    'bookings'    => (int) q("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn(),
    'enrollments' => (int) q("SELECT COUNT(*) FROM enrollments WHERE status='new'")->fetchColumn(),
    'messages'    => (int) q('SELECT COUNT(*) FROM messages WHERE is_read=0')->fetchColumn(),
];
$menu = [
    'dashboard'    => ['dashboard.php',    'fa-gauge-high',        'Dashboard'],
    'bookings'     => ['bookings.php',     'fa-calendar-check',    'Bookings'],
    'enrollments'  => ['enrollments.php',  'fa-user-graduate',     'Enrollments'],
    'services'     => ['services.php',     'fa-scissors',          'Services'],
    'categories'   => ['categories.php',   'fa-layer-group',       'Categories'],
    'courses'      => ['courses.php',      'fa-graduation-cap',    'Courses'],
    'gallery'      => ['gallery.php',      'fa-images',            'Gallery'],
    'testimonials' => ['testimonials.php', 'fa-star',              'Reviews'],
    'messages'     => ['messages.php',     'fa-envelope',          'Messages'],
    'settings'     => ['settings.php',     'fa-gear',              'Settings'],
];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e(($adminTitle ?? 'Admin') . ' · ' . setting('site_name')) ?></title>
<link rel="icon" href="../assets/img/favicon.svg" type="image/svg+xml">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600&family=Jost:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin.css?v=1">
</head>
<body>
<aside class="sidebar" id="sidebar">
    <a href="dashboard.php" class="side-brand"><span>RK</span> Admin Panel</a>
    <nav>
        <?php foreach ($menu as $key => [$href, $icon, $label]): ?>
            <a href="<?= $href ?>" class="<?= $adminPage === $key ? 'active' : '' ?>">
                <i class="fa-solid <?= $icon ?>"></i> <?= $label ?>
                <?php if (!empty($counts[$key])): ?><b class="pill"><?= $counts[$key] ?></b><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="side-foot">
        <a href="../index.php" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i> View website</a>
        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
</aside>
<div class="main">
    <header class="topbar">
        <button class="side-toggle" onclick="document.body.classList.toggle('side-open')" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
        <h1><?= e($adminTitle ?? 'Admin') ?></h1>
        <span class="who"><i class="fa-regular fa-user"></i> <?= e($_SESSION['admin_name'] ?? '') ?></span>
    </header>
    <div class="content">
        <?= flashes() ?>
