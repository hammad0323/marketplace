<?php
/**
 * Public site header. Pages set $pageTitle / $metaDescription / $metaKeywords
 * / $ogImage / $canonical before requiring this file; all have sensible
 * defaults. The default canonical strips ALL query params — correct for
 * listing/filter pages (avoids duplicate-content across filter combos), but
 * WRONG for detail pages keyed by a query param (e.g. ?slug=...): those
 * pages must set $canonical themselves (including their identifying param)
 * before requiring this file, or every record of that type would
 * canonicalize to the same generic URL.
 */
$pageTitle = $pageTitle ?? SITE_NAME . ' — Trusted Doctors, Online & In-Person';
$metaDescription = $metaDescription ?? get_setting('site_tagline', 'Book verified doctors for online and in-person consultations.');
$metaKeywords = $metaKeywords ?? 'telemedicine, doctors, online consultation, book appointment, healthcare';
$canonical = $canonical ?? (APP_URL . strtok($_SERVER['REQUEST_URI'], '?'));
$ogImage = $ogImage ?? APP_URL . '/assets/img/og-default.svg';
$user = current_user();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($metaDescription) ?>">
<meta name="keywords" content="<?= e($metaKeywords) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta name="robots" content="index, follow">

<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($metaDescription) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($pageTitle) ?>">
<meta name="twitter:description" content="<?= e($metaDescription) ?>">
<meta name="twitter:image" content="<?= e($ogImage) ?>">

<link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap"></noscript>
<link rel="stylesheet" href="/assets/fonts/remixicon/remixicon.css">
<link rel="stylesheet" href="/assets/css/style.css">
<?php if (!empty($extraHead)) echo $extraHead; ?>
</head>
<body data-logged-in="<?= is_logged_in() ? '1' : '0' ?>">
<div id="page-loader"><div class="loader-ring"></div></div>

<header class="navbar">
    <div class="navbar-inner glass">
        <a href="/" class="brand">
            <span class="brand-mark"><i class="ri-heart-pulse-fill"></i></span>
            <?= e(SITE_NAME) ?>
        </a>
        <nav class="nav-links">
            <a href="/" class="<?= basename($_SERVER['SCRIPT_NAME']) === 'index.php' ? 'active' : '' ?>">Home</a>
            <a href="/doctors" class="<?= basename($_SERVER['SCRIPT_NAME']) === 'doctors.php' ? 'active' : '' ?>">Find Doctors</a>
            <a href="/specializations" class="<?= basename($_SERVER['SCRIPT_NAME']) === 'specializations.php' ? 'active' : '' ?>">Specializations</a>
            <a href="/products" class="<?= in_array(basename($_SERVER['SCRIPT_NAME']), ['products.php', 'product-detail.php'], true) ? 'active' : '' ?>">Products</a>
            <a href="/blog" class="<?= basename($_SERVER['SCRIPT_NAME']) === 'blog.php' ? 'active' : '' ?>">Blog</a>
            <a href="/about" class="<?= basename($_SERVER['SCRIPT_NAME']) === 'about.php' ? 'active' : '' ?>">About</a>
            <a href="/contact" class="<?= basename($_SERVER['SCRIPT_NAME']) === 'contact.php' ? 'active' : '' ?>">Contact</a>
            <?php if (!$user): ?>
            <div class="nav-mobile-auth">
                <button class="btn btn-ghost btn-sm btn-block" onclick="openAuthModal('login')">Log In</button>
                <button class="btn btn-primary btn-sm btn-block" onclick="openAuthModal('register')">Get Started</button>
            </div>
            <?php endif; ?>
        </nav>
        <div class="nav-actions">
            <button class="theme-toggle" data-theme-toggle aria-label="Toggle dark mode"><i class="ri-moon-line"></i></button>
            <?php if ($user): ?>
                <div class="user-menu">
                    <button class="user-avatar-btn" data-dropdown-trigger="user-dropdown">
                        <img src="<?= e(avatar_url($user['avatar'], $user['full_name'])) ?>" alt="">
                        <span class="user-avatar-name" style="font-size:14px;font-weight:600;"><?= e(explode(' ', $user['full_name'])[0]) ?></span>
                        <i class="ri-arrow-down-s-line"></i>
                    </button>
                    <div class="dropdown-menu" id="user-dropdown">
                        <?php $home = $user['role'] === 'doctor' ? '/doctor/dashboard' : ($user['role'] === 'admin' ? '/admin/dashboard' : '/patient/dashboard'); ?>
                        <a href="<?= e($home) ?>"><i class="ri-dashboard-3-line"></i> Dashboard</a>
                        <?php if ($user['role'] === 'patient'): ?>
                        <a href="/patient/appointments"><i class="ri-calendar-check-line"></i> My Appointments</a>
                        <a href="/patient/profile"><i class="ri-user-line"></i> Profile</a>
                        <?php elseif ($user['role'] === 'doctor'): ?>
                        <a href="/doctor/appointments"><i class="ri-calendar-check-line"></i> Appointments</a>
                        <a href="/doctor/profile"><i class="ri-user-line"></i> Profile</a>
                        <?php endif; ?>
                        <div class="dropdown-divider"></div>
                        <a href="/logout"><i class="ri-logout-box-line"></i> Logout</a>
                    </div>
                </div>
            <?php else: ?>
                <button class="btn btn-ghost btn-sm nav-auth-btn" onclick="openAuthModal('login')">Log In</button>
                <button class="btn btn-primary btn-sm nav-auth-btn" onclick="openAuthModal('register')">Get Started</button>
            <?php endif; ?>
            <button class="btn-icon nav-toggle" data-nav-toggle aria-label="Menu"><i class="ri-menu-3-line"></i></button>
        </div>
    </div>
</header>
