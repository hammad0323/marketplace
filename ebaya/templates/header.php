<?php
/** Storefront header: <head>, announcement bar, navigation, search, mobile menu, mini-cart. */
if (!defined('EBAYA')) { http_response_code(403); exit; }

$bodyClass = $bodyClass ?? '';
$isHome = ($GLOBALS['route_path'] ?? '') === '/';
$transparent = $isHome && setting('header_transparent_home') && !empty($GLOBALS['hero_has_slides']);
$navItems = db_all('SELECT * FROM navigation_items WHERE menu = \'header\' AND is_visible = 1 ORDER BY sort_order, id');
$nav = [];
foreach ($navItems as $n) {
    if ($n['parent_id'] === null) $nav[(int)$n['id']] = $n + ['children' => []];
}
foreach ($navItems as $n) {
    if ($n['parent_id'] !== null && isset($nav[(int)$n['parent_id']])) $nav[(int)$n['parent_id']]['children'][] = $n;
}
$messages = array_values(array_filter(array_map('trim', preg_split('/\R/', (string)setting('announcement_text')))));
$logo = setting('logo');
$logoLight = setting('logo_light');
$siteName = setting('site_name', 'Ebaya');
$cartCount = cart_count();
$wishCount = count(wishlist_ids());
$navLayout = setting('nav_layout', 'center');
$current = '/' . trim($GLOBALS['route_path'] ?? '/', '/');
$isActive = fn($u) => $u !== '/' && $u !== '' && str_starts_with($current, rtrim(parse_url($u, PHP_URL_PATH) ?: '#', '/'));
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= seo_head() ?>
<meta name="theme-color" content="<?= e(setting('color_primary', '#354638')) ?>">
<?php if ($fav = setting('favicon')): ?><link rel="icon" href="<?= e(img_url($fav)) ?>"><?php else: ?><link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= e(google_fonts_url()) ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11.1.14/swiper-bundle.min.css">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<style><?= theme_css_vars() ?></style>
<?= analytics_head() ?>
</head>
<body class="<?= e(trim($bodyClass . ($transparent ? ' header-overlay' : '') . (setting('header_sticky') ? ' header-sticky' : '') . ' nav-' . $navLayout . ' btn-style-' . setting('btn_style', 'solid') . ' card-' . setting('card_style', 'minimal') . (setting('image_hover_zoom') ? ' img-zoom' : ''))) ?>"
      data-anim="<?= setting('anim_enabled') ? e(setting('anim_style', 'fade-up')) : 'none' ?>" data-parallax="<?= setting('parallax_enabled') ? '1' : '0' ?>">
<?php if (is_preview()): ?>
<div class="preview-bar">Preview mode — you are seeing unpublished drafts. <a href="<?= e(admin_url('homepage')) ?>">Back to admin</a> · <a href="?preview=0">Exit preview</a></div>
<?php endif; ?>
<a class="visually-hidden-focusable skip-link" href="#main">Skip to content</a>

<?php if (setting('announcement_enabled') && $messages): ?>
<div class="announcement" role="region" aria-label="Announcements">
  <div class="announcement-track" data-rotate>
    <?php foreach ($messages as $i => $m): ?>
      <div class="announcement-item<?= $i === 0 ? ' is-active' : '' ?>">
        <?php if ($l = setting('announcement_link')): ?><a href="<?= e(url($l)) ?>"><?= e($m) ?></a><?php else: ?><?= e($m) ?><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<header class="site-header" id="siteHeader">
  <div class="container-eb header-inner">
    <button class="icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileNav" aria-label="Open menu"><i class="bi bi-list"></i></button>
    <div class="header-tools header-tools-left d-none d-lg-flex">
      <?php if ($navLayout === 'center'): ?>
        <button class="icon-btn" type="button" data-search-open aria-label="Search"><i class="bi bi-search"></i><span class="tool-label">Search</span></button>
      <?php endif; ?>
    </div>
    <a class="brand" href="<?= e(url()) ?>" aria-label="<?= e($siteName) ?> home">
      <?php if ($logo): ?>
        <img src="<?= e(img_url($logo)) ?>" alt="<?= e($siteName) ?>" class="brand-logo brand-logo-dark" style="height:<?= (int)setting('logo_height', 42) ?>px">
        <?php if ($logoLight): ?><img src="<?= e(img_url($logoLight)) ?>" alt="" aria-hidden="true" class="brand-logo brand-logo-light" style="height:<?= (int)setting('logo_height', 42) ?>px"><?php endif; ?>
      <?php else: ?>
        <span class="brand-word"><?= e($siteName) ?></span>
        <span class="brand-tag"><?= e(setting('tagline')) ?></span>
      <?php endif; ?>
    </a>
    <?php if ($navLayout === 'left'): ?>
    <nav class="main-nav main-nav-inline d-none d-lg-block" aria-label="Main">
      <?php include __DIR__ . '/nav-items.php'; ?>
    </nav>
    <?php endif; ?>
    <div class="header-tools">
      <?php if ($navLayout === 'left'): ?>
        <button class="icon-btn d-none d-lg-inline-flex" type="button" data-search-open aria-label="Search"><i class="bi bi-search"></i></button>
      <?php endif; ?>
      <button class="icon-btn d-lg-none" type="button" data-search-open aria-label="Search"><i class="bi bi-search"></i></button>
      <a class="icon-btn d-none d-sm-inline-flex" href="<?= e(url(customer_id() ? 'account' : 'account/login')) ?>" aria-label="Account"><i class="bi bi-person"></i></a>
      <a class="icon-btn" href="<?= e(url('wishlist')) ?>" aria-label="Wishlist"><i class="bi bi-heart"></i><span class="count-badge" data-wish-count <?= $wishCount ? '' : 'hidden' ?>><?= $wishCount ?></span></a>
      <button class="icon-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#miniCart" aria-label="Shopping bag"><i class="bi bi-bag"></i><span class="count-badge" data-cart-count <?= $cartCount ? '' : 'hidden' ?>><?= $cartCount ?></span></button>
    </div>
  </div>
  <?php if ($navLayout === 'center'): ?>
  <nav class="main-nav d-none d-lg-block" aria-label="Main">
    <div class="container-eb"><?php include __DIR__ . '/nav-items.php'; ?></div>
  </nav>
  <?php endif; ?>
</header>

<!-- Search overlay -->
<div class="search-overlay" id="searchOverlay" aria-hidden="true">
  <div class="container-eb">
    <form action="<?= e(url('search')) ?>" method="get" class="search-form" role="search">
      <label for="searchInput" class="visually-hidden">Search</label>
      <input type="search" name="q" id="searchInput" placeholder="Search abayas, embroidery, crochet…" autocomplete="off" maxlength="100">
      <button type="button" class="icon-btn" data-search-close aria-label="Close search"><i class="bi bi-x-lg"></i></button>
    </form>
    <div class="search-results" id="searchResults" aria-live="polite"></div>
  </div>
</div>

<!-- Mobile navigation -->
<div class="offcanvas offcanvas-start mobile-nav <?= setting('mobile_nav') === 'fullscreen' ? 'mobile-nav-full' : '' ?>" tabindex="-1" id="mobileNav" aria-label="Menu">
  <div class="offcanvas-header">
    <span class="brand-word small"><?= e($siteName) ?></span>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <ul class="mobile-menu">
      <?php foreach ($nav as $n): ?>
        <li>
          <?php if ($n['children']): ?>
            <button class="mobile-menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#mm<?= (int)$n['id'] ?>" aria-expanded="false"><?= e($n['label']) ?><i class="bi bi-plus"></i></button>
            <ul class="collapse mobile-submenu" id="mm<?= (int)$n['id'] ?>">
              <li><a href="<?= e(url($n['url'])) ?>">View all</a></li>
              <?php foreach ($n['children'] as $c): ?><li><a href="<?= e(url($c['url'])) ?>"><?= e($c['label']) ?></a></li><?php endforeach; ?>
            </ul>
          <?php else: ?>
            <a href="<?= e(url($n['url'])) ?>"<?= $n['text_color'] ? ' style="color:' . e($n['text_color']) . '"' : '' ?>><?= e($n['label']) ?></a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <div class="mobile-nav-foot">
      <a href="<?= e(url(customer_id() ? 'account' : 'account/login')) ?>"><i class="bi bi-person"></i> <?= customer_id() ? 'My account' : 'Sign in / Register' ?></a>
      <a href="<?= e(url('wishlist')) ?>"><i class="bi bi-heart"></i> Wishlist</a>
      <a href="<?= e(url('track-order')) ?>"><i class="bi bi-truck"></i> Track order</a>
      <?php if ($ig = setting('social_instagram')): ?><a href="<?= e($ig) ?>" target="_blank" rel="noopener"><i class="bi bi-instagram"></i> Instagram</a><?php endif; ?>
    </div>
  </div>
</div>

<!-- Mini cart -->
<div class="offcanvas offcanvas-end mini-cart" tabindex="-1" id="miniCart" aria-labelledby="miniCartTitle">
  <div class="offcanvas-header">
    <h2 class="offcanvas-title h5" id="miniCartTitle">Your Bag</h2>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body" id="miniCartBody">
    <div class="skeleton-line"></div><div class="skeleton-line w-75"></div><div class="skeleton-line w-50"></div>
  </div>
</div>

<main id="main">
<?php $fl = flashes(); if ($fl): ?>
  <div class="container-eb flash-wrap">
    <?php foreach ($fl as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert"><?= e($f['msg']) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
