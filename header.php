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
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= mp_e($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/global.css">
    <?php if ($theme === 'artisan'): ?>
        <link rel="stylesheet" href="/assets/css/artisan-theme.css">
    <?php elseif ($theme === 'business'): ?>
        <link rel="stylesheet" href="/assets/css/business-theme.css">
    <?php endif; ?>
</head>
<body class="theme-<?= mp_e($theme) ?>">

<header class="site-nav">
    <div class="site-nav-inner">
        <a href="/" class="site-brand"><?= mp_e(SITE_NAME) ?></a>

        <nav class="marketplace-nav">
            <a href="/artisan.php">Artisan Marketplace</a>
            <a href="/business.php">Business Shops</a>
            <a href="/official-store.php">Official Store</a>
        </nav>

        <form method="get" action="/search.php" class="site-search">
            <input type="search" name="q" placeholder="Search all marketplaces&hellip;" value="<?= mp_e($_GET['q'] ?? '') ?>">
            <button type="submit">Search</button>
        </form>

        <nav class="account-nav">
            <?php if (mp_current_vendor()): ?>
                <a href="/vendor-dashboard.php">My Store</a>
            <?php else: ?>
                <a href="/vendor-login.php">Vendor Login</a>
                <a href="/vendor-register.php" class="cta-link">Sell With Us</a>
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
