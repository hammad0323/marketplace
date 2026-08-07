<?php
/**
 * Shared page header for the public site. Each page sets $pageTitle
 * and $theme ('main' | 'artisan' | 'business') before requiring this
 * file. Includes the site nav and flash messages inline.
 */
$theme = $theme ?? 'main';
$siteName = mp_get_setting('site_name', SITE_NAME);
$pageTitle = $pageTitle ?? $siteName;
$navCustomer = mp_current_customer();
$navCartCount = $navCustomer ? mp_cart_item_count($navCustomer['id']) : 0;

// The marketplace-type nav is driven by this tenant's own
// marketplace_types rows (seeded at signup, editable per tenant) —
// not a hardcoded 3-link list. The URL structure itself stays fixed
// (every tenant's rows use the same 3 slugs mapping onto the same 3
// folders); only the label text is dynamic per tenant.
$navMarketplaceRoutes = ['artisan' => ROUTE_ARTISAN, 'business' => ROUTE_BUSINESS, 'official' => ROUTE_OFFICIAL_STORE];
$navMarketplaceTypes = mp_current_tenant() ? mp_all_marketplace_types() : [];

// Maintenance mode blocks the public site for everyone except a
// logged-in admin (who needs to be able to reach admin/settings.php
// to turn it back off) or an already-logged-in vendor checking their
// own dashboard.
if (mp_get_setting('maintenance_mode', false) && !mp_current_admin()) {
    http_response_code(503);
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= mp_e($siteName) ?> — Down for Maintenance</title>
        <link rel="stylesheet" href="<?= mp_e(ROUTE_ASSETS) ?>css/global.css">
    </head>
    <body class="theme-main">
        <main class="site-main" style="text-align:center; padding: 6rem 1rem;">
            <span class="empty-state-icon">🛠️</span>
            <h1><?= mp_e($siteName) ?> is down for maintenance</h1>
            <p>We'll be back shortly. Thanks for your patience.</p>
            <p><a href="<?= mp_e(ROUTE_ADMIN) ?>login.php" style="font-size:.85rem; color:var(--ink-500);">Admin login</a></p>
        </main>
    </body>
    </html>
    <?php
    exit;
}
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
        <a href="<?= mp_e(ROUTE_HOME) ?>" class="site-brand"><?= mp_e($siteName) ?></a>

        <nav class="marketplace-nav">
            <?php foreach ($navMarketplaceTypes as $navType): ?>
                <?php if (isset($navMarketplaceRoutes[$navType['slug']])): ?>
                    <a href="<?= mp_e($navMarketplaceRoutes[$navType['slug']]) ?>index.php"><?= mp_e($navType['name']) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <form method="get" action="<?= mp_e(ROUTE_STORE) ?>search.php" class="site-search">
            <input type="search" name="q" placeholder="Search all marketplaces&hellip;" value="<?= mp_e($_GET['q'] ?? '') ?>">
            <button type="submit">Search</button>
        </form>

        <nav class="account-nav">
            <a href="<?= mp_e(ROUTE_CART) ?>view.php" class="cart-link" aria-label="Cart">
                🛒<?php if ($navCartCount > 0): ?><span class="cart-badge"><?= $navCartCount ?></span><?php endif; ?>
            </a>

            <?php if ($navCustomer): ?>
                <div class="account-menu">
                    <button type="button" class="account-menu-trigger">
                        <?= mp_e($navCustomer['name']) ?><?php if (!$navCustomer['email_verified_at']): ?><span class="cart-badge" style="position:static; margin-left:.35rem;">!</span><?php endif; ?> ▾
                    </button>
                    <div class="account-menu-panel">
                        <a href="<?= mp_e(ROUTE_CUSTOMER) ?>orders.php">My Orders</a>
                        <?php if (!$navCustomer['email_verified_at']): ?>
                            <form method="post" action="<?= mp_e(ROUTE_CUSTOMER) ?>resend-verification.php"><?= mp_csrf_field() ?><button type="submit" class="link-button">Verify Email</button></form>
                        <?php endif; ?>
                        <form method="post" action="<?= mp_e(ROUTE_CUSTOMER) ?>logout.php"><?= mp_csrf_field() ?><button type="submit" class="link-button">Logout</button></form>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= mp_e(ROUTE_CUSTOMER) ?>login.php">Login</a>
            <?php endif; ?>

            <?php if (mp_current_vendor()): ?>
                <a href="<?= mp_e(ROUTE_VENDOR) ?>dashboard.php">My Store</a>
            <?php else: ?>
                <a href="<?= mp_e(ROUTE_VENDOR) ?>login.php" class="vendor-login-link">Vendor Login</a>
                <a href="<?= mp_e(ROUTE_VENDOR) ?>register.php" class="cta-link">Sell With Us</a>
            <?php endif; ?>
        </nav>
    </div>
    <div class="scroll-progress"></div>
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
