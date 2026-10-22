<?php
/**
 * Storefront header: <head>, announcement bar, navigation with mega menus,
 * search overlay, mobile drawer. Pages set $bodyClass and call seo_set() first.
 */
$bodyClass = $bodyClass ?? '';
$headerVariant = $headerVariant ?? 'solid'; // 'transparent' over a hero banner
$cartCount = cart_count();
$wishCount = count(wishlist_product_ids());
$customer = current_customer();
$logo = setting('logo', 'assets/images/brand/logo-round.webp');
$navLayout = theme('theme_nav_layout');
$announcements = array_values(array_filter(array_map('trim', explode("\n", (string) setting('announcement_items', '')))));
$animations = setting_bool('theme_animations', true);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="theme-color" content="<?= e(safe_color(theme('theme_header_bg'))) ?>">
<?php seo_head(); ?>
<link rel="icon" href="<?= e(img_url(setting('favicon', 'assets/images/brand/favicon.png'))) ?>">
<link rel="apple-touch-icon" href="<?= e(url('assets/images/brand/apple-touch-icon.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://images.unsplash.com">
<link rel="stylesheet" href="<?= e(google_fonts_url()) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/store.css')) ?>">
<style><?= theme_css_vars() ?></style>
<?php analytics_head(); ?>
</head>
<body class="<?= e(trim($bodyClass . ($animations ? ' uc-anim' : ' uc-no-anim') . ' header-' . $headerVariant . (theme('theme_sticky_header') === '1' ? ' header-sticky' : ''))) ?>">
<a class="visually-hidden-focusable uc-skip" href="#main">Skip to content</a>

<?php if (setting_bool('announcement_enabled') && $announcements): ?>
<div class="uc-announce" role="region" aria-label="Announcements">
    <div class="uc-announce-track" data-rotator>
        <?php foreach ($announcements as $i => $a): ?>
            <a class="uc-announce-item<?= $i === 0 ? ' is-active' : '' ?>" href="<?= e(url(setting('announcement_link', '') ?: '')) ?>"><?= e($a) ?></a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<header class="uc-header nav-<?= e($navLayout) ?>" id="ucHeader">
    <div class="uc-header-inner uc-container-wide">
        <button class="uc-icon-btn d-lg-none" type="button" data-drawer-open="ucMobileNav" aria-label="Open menu"><i class="bi bi-list"></i></button>

        <a class="uc-logo" href="<?= e(url()) ?>" aria-label="<?= e(store_name()) ?> home">
            <img src="<?= e(img_url($logo)) ?>" alt="<?= e(store_name()) ?> logo" width="46" height="46">
            <?php if (setting_bool('show_brand_text', true)): ?>
                <span class="uc-logo-text"><span>Urban</span><small>Collections</small></span>
            <?php endif; ?>
        </a>

        <nav class="uc-nav d-none d-lg-block" aria-label="Main">
            <ul class="uc-nav-list">
                <?php foreach (nav_items('header') as $item):
                    $isMega = (int) $item['is_mega'] && $item['category_id'];
                    $megaCats = $isMega ? category_children((int) $item['category_id']) : [];
                    $hasDrop = $item['children'] || $megaCats;
                ?>
                <li class="uc-nav-item<?= $hasDrop ? ' has-drop' : '' ?><?= $isMega && $megaCats ? ' is-mega' : '' ?><?= nav_active($item) ? ' is-active' : '' ?>">
                    <a class="uc-nav-link<?= (int) $item['highlight'] ? ' is-highlight' : '' ?>" href="<?= e(nav_url($item)) ?>"<?= (int) $item['open_new_tab'] ? ' target="_blank" rel="noopener"' : '' ?><?= $hasDrop ? ' aria-haspopup="true"' : '' ?>><?= e($item['label']) ?></a>
                    <?php if ($isMega && $megaCats): ?>
                        <div class="uc-mega" role="menu">
                            <div class="uc-mega-inner uc-container">
                                <div class="uc-mega-cols">
                                    <?php foreach ($megaCats as $mc): $grand = category_children((int) $mc['id']); ?>
                                        <div class="uc-mega-col">
                                            <a class="uc-mega-title" href="<?= e(category_url($mc)) ?>"><?= e($mc['name']) ?></a>
                                            <?php if ($grand): ?>
                                                <ul>
                                                    <?php foreach ($grand as $g): ?>
                                                        <li><a href="<?= e(category_url($g)) ?>"><?= e($g['name']) ?></a></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if ($item['mega_image']): ?>
                                    <a class="uc-mega-feature" href="<?= e(nav_url($item)) ?>">
                                        <img src="<?= e(img_sized($item['mega_image'], 700)) ?>" alt="<?= e($item['label']) ?>" loading="lazy">
                                        <span>Shop all <?= e($item['label']) ?> <i class="bi bi-arrow-right"></i></span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php elseif ($item['children']): ?>
                        <ul class="uc-dropdown" role="menu">
                            <?php foreach ($item['children'] as $child): ?>
                                <li><a href="<?= e(nav_url($child)) ?>"<?= (int) $child['open_new_tab'] ? ' target="_blank" rel="noopener"' : '' ?>><?= e($child['label']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="uc-header-actions">
            <button class="uc-icon-btn" type="button" data-search-open aria-label="Search"><i class="bi bi-search"></i></button>
            <a class="uc-icon-btn d-none d-sm-inline-flex" href="<?= e(url($customer ? 'account' : 'account/login')) ?>" aria-label="<?= $customer ? 'My account' : 'Sign in' ?>"><i class="bi bi-person"></i></a>
            <a class="uc-icon-btn" href="<?= e(url('wishlist')) ?>" aria-label="Wishlist"><i class="bi bi-heart"></i><span class="uc-count" data-wish-count<?= $wishCount ? '' : ' hidden' ?>><?= $wishCount ?></span></a>
            <a class="uc-icon-btn" href="<?= e(url('cart')) ?>" data-cart-open aria-label="Shopping bag"><i class="bi bi-bag"></i><span class="uc-count" data-cart-count<?= $cartCount ? '' : ' hidden' ?>><?= $cartCount ?></span></a>
        </div>
    </div>
</header>

<!-- Search overlay -->
<div class="uc-search" id="ucSearch" aria-hidden="true">
    <div class="uc-search-inner uc-container">
        <form action="<?= e(url('search')) ?>" method="get" role="search" class="uc-search-form">
            <i class="bi bi-search"></i>
            <input type="search" name="q" placeholder="Search shirts, jeans, sneakers…" autocomplete="off" aria-label="Search products" data-search-input>
            <button type="button" class="uc-icon-btn" data-search-close aria-label="Close search"><i class="bi bi-x-lg"></i></button>
        </form>
        <div class="uc-search-results" data-search-results></div>
    </div>
</div>

<!-- Mobile navigation drawer -->
<aside class="uc-drawer uc-drawer--<?= theme('theme_mobile_nav') === 'fullscreen' ? 'full' : 'left' ?>" id="ucMobileNav" aria-hidden="true" aria-label="Menu">
    <div class="uc-drawer-head">
        <span class="uc-drawer-title">Menu</span>
        <button class="uc-icon-btn" type="button" data-drawer-close aria-label="Close menu"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="uc-drawer-body">
        <ul class="uc-mnav">
            <?php foreach (nav_items('header') as $i => $item):
                $kids = $item['children'];
                if ((int) $item['is_mega'] && $item['category_id']) {
                    $kids = array_map(fn($c) => ['label' => $c['name'], 'url' => 'category/' . $c['slug'], 'open_new_tab' => 0], category_children((int) $item['category_id']));
                }
            ?>
                <li>
                    <?php if ($kids): ?>
                        <button class="uc-mnav-toggle" type="button" aria-expanded="false" data-accordion><?= e($item['label']) ?><i class="bi bi-plus-lg"></i></button>
                        <ul class="uc-mnav-sub">
                            <li><a href="<?= e(nav_url($item)) ?>">All <?= e($item['label']) ?></a></li>
                            <?php foreach ($kids as $k): ?>
                                <li><a href="<?= e(nav_url($k)) ?>"><?= e($k['label']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <a href="<?= e(nav_url($item)) ?>"><?= e($item['label']) ?></a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="uc-mnav-extra">
            <a href="<?= e(url($customer ? 'account' : 'account/login')) ?>"><i class="bi bi-person"></i> <?= $customer ? 'My Account' : 'Sign In / Register' ?></a>
            <a href="<?= e(url('wishlist')) ?>"><i class="bi bi-heart"></i> Wishlist</a>
            <a href="<?= e(url('track-order')) ?>"><i class="bi bi-truck"></i> Track Order</a>
            <a href="<?= e(url('contact')) ?>"><i class="bi bi-chat"></i> Contact</a>
        </div>
    </div>
</aside>

<!-- Cart drawer -->
<aside class="uc-drawer uc-drawer--right" id="ucCartDrawer" aria-hidden="true" aria-label="Shopping bag">
    <div class="uc-drawer-head">
        <span class="uc-drawer-title">Your Bag</span>
        <button class="uc-icon-btn" type="button" data-drawer-close aria-label="Close bag"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="uc-drawer-body" data-cart-drawer-body>
        <div class="uc-skeleton-list"><span></span><span></span><span></span></div>
    </div>
</aside>
<div class="uc-backdrop" data-backdrop></div>

<!-- Quick view -->
<div class="modal fade uc-modal" id="ucQuickView" tabindex="-1" aria-label="Quick view" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-md-down">
        <div class="modal-content">
            <button type="button" class="btn-close uc-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            <div class="modal-body p-0" data-quickview-body></div>
        </div>
    </div>
</div>

<?php if (!empty($GLOBALS['UC_PREVIEW'])): ?>
<div class="uc-preview-bar">Preview mode — showing draft sections and inactive slides. <a href="<?= e(url('admin/homepage/index.php')) ?>">Back to Homepage Builder</a></div>
<?php endif; ?>

<main id="main">
<?php foreach (flashes() as $f): ?>
    <div class="uc-flash uc-flash--<?= e($f['type']) ?>" role="status" data-flash><?= e($f['message']) ?></div>
<?php endforeach; ?>
