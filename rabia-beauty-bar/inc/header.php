<?php
/** Public site header. Pages set $pageTitle and $activeNav before including. */
$pageTitle = $pageTitle ?? '';
$activeNav = $activeNav ?? '';
$siteName  = setting('site_name', "Rabia Khan's Beauty Bar & Academy");
$nav = [
    'home'     => ['index.php', 'Home'],
    'services' => ['services.php', 'Services'],
    'courses'  => ['courses.php', 'Academy'],
    'gallery'  => ['index.php#gallery', 'Gallery'],
    'contact'  => ['contact.php', 'Contact'],
];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ? $pageTitle . ' | ' . $siteName : $siteName . ' — ' . setting('tagline')) ?></title>
<meta name="description" content="<?= e($metaDescription ?? setting('hero_text')) ?>">
<meta property="og:title" content="<?= e($siteName) ?>">
<meta property="og:image" content="assets/img/model.jpg">
<link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Great+Vibes&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css?v=1">
</head>
<body class="<?= e($bodyClass ?? '') ?>">

<div class="preloader" id="preloader">
    <div class="preloader-logo"><span>R</span><span>K</span></div>
    <div class="preloader-line"></div>
</div>
<div class="scroll-progress" id="scrollProgress"></div>

<header class="site-header" id="siteHeader">
    <div class="container nav-wrap">
        <a href="index.php" class="brand">
            <span class="brand-mark">RK</span>
            <span class="brand-text">
                <strong>Rabia Khan's</strong>
                <small>Beauty Bar &amp; Academy</small>
            </span>
        </a>
        <nav class="main-nav" id="mainNav">
            <?php foreach ($nav as $key => [$href, $label]): ?>
                <a href="<?= $href ?>" class="<?= $activeNav === $key ? 'active' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
            <a href="booking.php" class="btn btn-primary btn-sm nav-cta">Book Now</a>
        </nav>
        <button class="nav-toggle" id="navToggle" aria-label="Open menu"><span></span><span></span><span></span></button>
    </div>
</header>
<main>
