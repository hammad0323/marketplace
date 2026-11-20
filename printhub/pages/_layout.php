<?php
/**
 * Customer website layout. Mobile-first, works without an app. Language/direction attributes are set
 * here so Urdu (RTL) can be added by switching $LANG / $DIR.
 */
if (!defined('PH_ROOT')) {
    http_response_code(404);
    exit;
}

function c_header(string $title, array $o = []): void
{
    // $o: shop (row), theme (hex), description, back (url), active (shop|orders|cv)
    $shop = $o['shop'] ?? null;
    $theme = valid_color($o['theme'] ?? null, '#0F766E');
    $lang = $o['lang'] ?? 'en';
    $dir = $o['dir'] ?? 'ltr';
    $session = customer_session(false);
    $lastShop = $shop ?: ($session && $session['last_shop_id'] ? shop_by_id((int) $session['last_shop_id']) : null);
    $active = $o['active'] ?? '';
    ?><!doctype html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?> · <?= e(platform_name()) ?></title>
<meta name="description" content="<?= e($o['description'] ?? setting('platform_tagline', '')) ?>">
<meta name="theme-color" content="<?= e($theme) ?>">
<meta property="og:type" content="website"><meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($o['description'] ?? setting('platform_tagline', '')) ?>">
<meta property="og:image" content="<?= e($shop && $shop['logo_path'] ? url('media/' . $shop['logo_path']) : url('assets/img/icon-512.png')) ?>">
<meta name="csrf-api" content="xhr">
<link rel="manifest" href="<?= e(path('manifest.webmanifest')) ?>">
<link rel="icon" href="<?= e(path('assets/img/icon-192.png')) ?>">
<link rel="apple-touch-icon" href="<?= e(path('assets/img/icon-192.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
<?php foreach ($o['css'] ?? [] as $css): ?><link rel="stylesheet" href="<?= e(asset($css)) ?>"><?php endforeach; ?>
<link rel="stylesheet" href="<?= e(asset('css/customer.css')) ?>">
<style>:root { --c-brand: <?= e($theme) ?>; }</style>
<?php if (!empty($o['head'])) echo $o['head']; ?>
</head>
<body class="c-body<?= !empty($o['body_class']) ? ' ' . e($o['body_class']) : '' ?>">
<header class="c-top">
    <div class="c-top-inner">
        <?php if (!empty($o['back'])): ?><a class="c-icon-btn" href="<?= e($o['back']) ?>" aria-label="Back"><i class="bi bi-arrow-left"></i></a><?php endif; ?>
        <a class="c-brand" href="<?= e($shop ? path('shop/' . $shop['shop_code']) : path('')) ?>">
            <?php if ($shop && $shop['logo_path']): ?><img src="<?= e(path('media/' . $shop['logo_path'])) ?>" alt="" width="32" height="32"><?php else: ?><span class="c-brand-mark"><i class="bi bi-printer-fill"></i></span><?php endif; ?>
            <span><?= e($shop['name'] ?? platform_name()) ?></span>
        </a>
        <nav class="c-top-nav d-none d-md-flex">
            <?php if ($lastShop): ?><a class="<?= $active === 'shop' ? 'is-active' : '' ?>" href="<?= e(path('shop/' . $lastShop['shop_code'])) ?>">Shop</a><?php endif; ?>
            <a class="<?= $active === 'orders' ? 'is-active' : '' ?>" href="<?= e(path('orders')) ?>">My orders</a>
            <a class="<?= $active === 'cv' ? 'is-active' : '' ?>" href="<?= e(path('cv')) ?>">CV maker</a>
        </nav>
        <a class="c-icon-btn ms-auto ms-md-0" href="<?= e(path('orders')) ?>" aria-label="Notifications" data-c-bell><i class="bi bi-bell"></i><em data-c-unread hidden></em></a>
    </div>
</header>
<?php if (setting('show_install_prompt', '1') === '1' && (setting('play_store_url') || setting('app_store_url'))): ?>
<div class="c-app-banner" data-app-banner hidden><i class="bi bi-phone"></i><span>Get the <?= e(platform_name()) ?> app for faster ordering — or keep using the website.</span>
    <a class="c-btn c-btn-sm" data-app-link href="<?= e(setting('play_store_url') ?: setting('app_store_url')) ?>" data-play="<?= e(setting('play_store_url')) ?>" data-ios="<?= e(setting('app_store_url')) ?>" rel="noopener">Get app</a>
    <button type="button" class="c-icon-btn" data-app-dismiss aria-label="Close"><i class="bi bi-x"></i></button></div>
<?php endif; ?>
<div class="c-pwa-banner" data-pwa-banner hidden><i class="bi bi-download"></i><span>Add <?= e(platform_name()) ?> to your home screen for quick access.</span><button type="button" class="c-btn c-btn-sm" data-pwa-install>Install</button><button type="button" class="c-icon-btn" data-pwa-dismiss aria-label="Close"><i class="bi bi-x"></i></button></div>
<main class="c-main" id="main">
<?php
}

function c_footer(array $scripts = [], array $o = []): void
{
    $session = customer_session(false);
    $lastShop = $session && $session['last_shop_id'] ? shop_by_id((int) $session['last_shop_id']) : null;
    $active = $o['active'] ?? '';
    ?></main>
<footer class="c-footer"><div class="c-wrap"><p><?= e(platform_name()) ?> · <?= e(setting('platform_tagline')) ?></p>
    <p><a href="<?= e(path('privacy')) ?>">Privacy</a> · <a href="<?= e(path('track')) ?>">Track an order</a> · <a href="<?= e(path('cv')) ?>">CV maker</a><?= setting('support_phone') ? ' · Help: ' . e(setting('support_phone')) : '' ?></p></div></footer>
<nav class="c-bottom-nav d-md-none" aria-label="Main">
    <a class="<?= $active === 'shop' ? 'is-active' : '' ?>" href="<?= e($lastShop ? path('shop/' . $lastShop['shop_code']) : path('')) ?>"><i class="bi bi-shop"></i><span>Shop</span></a>
    <a class="<?= $active === 'print' ? 'is-active' : '' ?>" href="<?= e($lastShop ? path('shop/' . $lastShop['shop_code'] . '/print') : path('')) ?>"><i class="bi bi-printer"></i><span>Print</span></a>
    <a class="<?= $active === 'orders' ? 'is-active' : '' ?>" href="<?= e(path('orders')) ?>"><i class="bi bi-receipt"></i><span>Orders</span></a>
    <a class="<?= $active === 'cv' ? 'is-active' : '' ?>" href="<?= e(path('cv')) ?>"><i class="bi bi-file-person"></i><span>CV</span></a>
</nav>
<script>window.PH = <?= json_encode(['base' => path(''), 'api' => path('api/v1'), 'name' => platform_name(), 'sw' => path('sw.js')] + ($o['js'] ?? []), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/sweetalert2/sweetalert2.all.min.js')) ?>"></script>
<script src="<?= e(asset('js/customer.js')) ?>"></script>
<?php foreach ($scripts as $s): ?><script src="<?= e(asset($s)) ?>"></script><?php endforeach; ?>
<?php if (!empty($o['modules'])) echo $o['modules']; ?>
</body></html><?php
}
