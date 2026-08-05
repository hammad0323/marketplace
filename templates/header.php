<?php
/**
 * Shared page header for the public site. Each page sets $pageTitle
 * and $theme ('main' | 'artisan' | 'business') before requiring this
 * file. Includes the site nav and flash messages inline.
 */
$theme = $theme ?? 'main';
$pageTitle = $pageTitle ?? SITE_NAME;
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>document.documentElement.className = 'js';</script>
    <title><?= mp_e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= mp_e(ROUTE_ASSETS) ?>css/global.css">
    <?php if ($theme === 'artisan'): ?>
        <link rel="stylesheet" href="<?= mp_e(ROUTE_ASSETS) ?>css/artisan-theme.css">
    <?php elseif ($theme === 'business'): ?>
        <link rel="stylesheet" href="<?= mp_e(ROUTE_ASSETS) ?>css/business-theme.css">
    <?php endif; ?>
</head>
<body class="theme-<?= mp_e($theme) ?>">

<header class="site-nav">
    <div class="site-nav-inner">
        <a href="<?= mp_e(ROUTE_HOME) ?>" class="site-brand"><?= mp_e(SITE_NAME) ?></a>

        <nav class="marketplace-nav">
            <a href="<?= mp_e(ROUTE_ARTISAN) ?>index.php">Artisan Marketplace</a>
            <a href="<?= mp_e(ROUTE_BUSINESS) ?>index.php">Business Shops</a>
            <a href="<?= mp_e(ROUTE_OFFICIAL_STORE) ?>index.php">Official Store</a>
        </nav>

        <form method="get" action="<?= mp_e(ROUTE_STORE) ?>search.php" class="site-search">
            <input type="search" name="q" placeholder="Search all marketplaces&hellip;" value="<?= mp_e($_GET['q'] ?? '') ?>">
            <button type="submit">Search</button>
        </form>

        <nav class="account-nav">
            <?php if (mp_current_vendor()): ?>
                <a href="<?= mp_e(ROUTE_VENDOR) ?>dashboard.php">My Store</a>
            <?php else: ?>
                <a href="<?= mp_e(ROUTE_VENDOR) ?>login.php">Vendor Login</a>
                <a href="<?= mp_e(ROUTE_VENDOR) ?>register.php" class="cta-link">Sell With Us</a>
            <?php endif; ?>
        </nav>
    </div>
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

<main class="site-main <?= $theme !== 'main' ? mp_e($theme) . '-main' : '' ?>">
