<?php
/**
 * Storefront header: announcement bar, logo, delivery area, smart search,
 * account / wishlist / cart, desktop mega menu and mobile accordion menu.
 */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
$customer = current_customer();
$tree = cache_get('menu_tree', 600, function () { return category_tree(null, 0, 3); });
$zone = selected_zone();
$cartCount = cart_count();
$wishCount = count(wishlist_ids());
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0f766e">
<?= seo_head() ?>
<link rel="icon" href="<?= url('favicon.ico') ?>" sizes="any">
<link rel="icon" href="<?= url('assets/images/favicon.svg') ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= url('assets/images/apple-touch-icon.png') ?>">
<link rel="preload" href="<?= url('assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= asset('css/style.css') ?>">
<script>window.FS = {base: <?= json_encode(base_path()) ?>, csrf: <?= json_encode(csrf_token()) ?>, currency: <?= json_encode(setting('currency_symbol', 'Rs.')) ?>, loggedIn: <?= $customer ? 'true' : 'false' ?>};</script>
</head>
<body class="<?= e(page_get('body_class')) ?>">
<a class="visually-hidden-focusable skip-link" href="#main">Skip to content</a>
<?php if ($ann = setting('announcement')): ?>
<div class="announce-bar"><div class="container text-center small"><i class="bi bi-truck me-1"></i><?= e($ann) ?></div></div>
<?php endif; ?>

<header class="site-header" id="siteHeader">
  <div class="header-main">
    <div class="container d-flex align-items-center gap-2 gap-lg-3">
      <button class="btn btn-icon d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-label="Open menu"><i class="bi bi-list"></i></button>
      <a href="<?= url('') ?>" class="brand" aria-label="<?= e(setting('site_name', 'Fahim Store')) ?> home">
        <img src="<?= url('assets/images/logo.svg') ?>" alt="<?= e(setting('site_name', 'Fahim Store')) ?>" width="200" height="48" class="brand-logo d-none d-sm-block">
        <img src="<?= url('assets/images/logo-icon.svg') ?>" alt="<?= e(setting('site_name', 'Fahim Store')) ?>" width="40" height="40" class="d-sm-none">
      </a>
      <button type="button" class="location-btn d-none d-xl-flex" data-bs-toggle="modal" data-bs-target="#zoneModal">
        <i class="bi bi-geo-alt-fill"></i>
        <span><small>Deliver to</small><strong id="zoneLabel"><?= $zone ? e($zone['area'] . ', ' . $zone['city']) : 'Select your area' ?></strong></span>
      </button>

      <form class="search-box flex-grow-1 d-none d-md-block" action="<?= url('search/') ?>" method="get" role="search" autocomplete="off">
        <div class="input-group">
          <input type="search" name="q" class="form-control js-search-input" placeholder="Search books, atta, oil, registers, ISBN or SKU…" value="<?= e($_GET['q'] ?? '') ?>" aria-label="Search products" maxlength="100">
          <button class="btn btn-brand" type="submit" aria-label="Search"><i class="bi bi-search"></i></button>
        </div>
        <div class="search-suggest shadow" role="listbox"></div>
      </form>

      <div class="header-actions ms-auto d-flex align-items-center">
        <button class="btn btn-icon d-md-none" type="button" data-bs-toggle="modal" data-bs-target="#searchOverlay" aria-label="Search"><i class="bi bi-search"></i></button>
        <div class="dropdown">
          <a href="<?= url($customer ? 'my-account/' : 'login/') ?>" class="btn btn-icon header-link" <?= $customer ? 'data-bs-toggle="dropdown" aria-expanded="false"' : '' ?>>
            <i class="bi bi-person"></i><span class="d-none d-lg-inline"><?= $customer ? e(explode(' ', $customer['name'])[0]) : 'Login' ?></span>
          </a>
          <?php if ($customer): ?>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            <li><a class="dropdown-item" href="<?= url('my-account/') ?>"><i class="bi bi-speedometer2 me-2"></i>My Account</a></li>
            <li><a class="dropdown-item" href="<?= url('my-account/orders/') ?>"><i class="bi bi-bag me-2"></i>My Orders</a></li>
            <li><a class="dropdown-item" href="<?= url('my-account/rashan-lists/') ?>"><i class="bi bi-basket me-2"></i>Rashan Lists</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= url('logout/') ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
          </ul>
          <?php endif; ?>
        </div>
        <a href="<?= url('wishlist/') ?>" class="btn btn-icon header-link position-relative" aria-label="Wishlist">
          <i class="bi bi-heart"></i><span class="badge-count js-wish-count<?= $wishCount ? '' : ' d-none' ?>"><?= $wishCount ?></span>
        </a>
        <a href="<?= url('cart/') ?>" class="btn btn-icon header-link position-relative" aria-label="Cart">
          <i class="bi bi-cart3"></i><span class="badge-count js-cart-count<?= $cartCount ? '' : ' d-none' ?>"><?= $cartCount ?></span>
          <span class="d-none d-lg-inline">Cart</span>
        </a>
      </div>
    </div>
  </div>

  <nav class="main-nav d-none d-lg-block" aria-label="Main">
    <div class="container d-flex align-items-center">
      <div class="mega-wrap">
        <button class="btn mega-toggle" type="button" aria-haspopup="true"><i class="bi bi-grid-3x3-gap-fill me-2"></i>All Categories<i class="bi bi-chevron-down ms-2 small"></i></button>
        <div class="mega-menu shadow-lg">
          <ul class="mega-roots list-unstyled mb-0">
            <?php foreach ($tree as $i => $root): ?>
            <li class="mega-root<?= $i === 0 ? ' active' : '' ?>" data-target="mega-<?= (int) $root['id'] ?>">
              <a href="<?= e(category_url($root)) ?>"><i class="bi <?= e($root['icon'] ?: 'bi-tag') ?> me-2"></i><?= e($root['name']) ?><i class="bi bi-chevron-right ms-auto small"></i></a>
            </li>
            <?php endforeach; ?>
          </ul>
          <div class="mega-panels">
            <?php foreach ($tree as $i => $root): ?>
            <div class="mega-panel<?= $i === 0 ? ' active' : '' ?>" id="mega-<?= (int) $root['id'] ?>">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="<?= e(category_url($root)) ?>" class="h6 mb-0 text-brand fw-bold">Shop all <?= e($root['name']) ?> <i class="bi bi-arrow-right"></i></a>
              </div>
              <div class="mega-cols">
                <?php foreach ($root['children'] as $sub): ?>
                <div class="mega-col">
                  <a href="<?= e(category_url($sub)) ?>" class="mega-sub"><?= e($sub['name']) ?></a>
                  <?php if ($sub['children']): ?>
                  <ul class="list-unstyled">
                    <?php foreach ($sub['children'] as $child): ?>
                    <li><a href="<?= e(category_url($child)) ?>"><?= e($child['name']) ?></a></li>
                    <?php endforeach; ?>
                  </ul>
                  <?php endif; ?>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <ul class="nav main-links">
        <?php foreach (array_slice($tree, 0, 5) as $root): ?>
        <li class="nav-item dropdown-hover">
          <a class="nav-link" href="<?= e(category_url($root)) ?>"><?= e($root['name']) ?></a>
          <?php if ($root['children']): ?>
          <div class="hover-panel shadow">
            <?php foreach ($root['children'] as $sub): ?>
            <a href="<?= e(category_url($sub)) ?>"><?= e($sub['name']) ?></a>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
        <li class="nav-item"><a class="nav-link nav-highlight" href="<?= url('monthly-rashan/') ?>"><i class="bi bi-basket2-fill me-1"></i>Monthly Rashan</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= url('offers/') ?>"><i class="bi bi-lightning-charge-fill text-warning me-1"></i>Offers</a></li>
      </ul>
      <a class="ms-auto small text-muted text-decoration-none" href="tel:<?= e(preg_replace('/[^0-9+]/', '', setting('store_phone'))) ?>"><i class="bi bi-telephone me-1"></i><?= e(setting('store_phone')) ?></a>
    </div>
  </nav>
</header>

<!-- Mobile menu (accordion) -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileMenu" aria-labelledby="mobileMenuLabel">
  <div class="offcanvas-header border-bottom">
    <img src="<?= url('assets/images/logo.svg') ?>" alt="Fahim Store" height="40" width="168" id="mobileMenuLabel">
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body p-0">
    <button type="button" class="mobile-zone w-100" data-bs-toggle="modal" data-bs-target="#zoneModal"><i class="bi bi-geo-alt-fill text-brand me-2"></i><span class="js-zone-text"><?= $zone ? e($zone['area'] . ', ' . $zone['city']) : 'Select delivery area' ?></span></button>
    <a href="<?= url('monthly-rashan/') ?>" class="mobile-link fw-semibold text-brand"><i class="bi bi-basket2-fill me-2"></i>Build Monthly Rashan</a>
    <a href="<?= url('offers/') ?>" class="mobile-link"><i class="bi bi-lightning-charge me-2"></i>Offers &amp; Deals</a>
    <div class="accordion accordion-flush" id="mobileCats">
      <?php foreach ($tree as $root): $rid = 'mc' . (int) $root['id']; ?>
      <div class="accordion-item">
        <h2 class="accordion-header">
          <?php if ($root['children']): ?>
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $rid ?>"><i class="bi <?= e($root['icon'] ?: 'bi-tag') ?> me-2"></i><?= e($root['name']) ?></button>
          <?php else: ?>
          <a class="accordion-button no-caret collapsed" href="<?= e(category_url($root)) ?>"><?= e($root['name']) ?></a>
          <?php endif; ?>
        </h2>
        <?php if ($root['children']): ?>
        <div id="<?= $rid ?>" class="accordion-collapse collapse" data-bs-parent="#mobileCats">
          <div class="accordion-body p-0">
            <a class="mobile-sub fw-semibold" href="<?= e(category_url($root)) ?>">All <?= e($root['name']) ?></a>
            <?php foreach ($root['children'] as $sub): $sid = 'ms' . (int) $sub['id']; ?>
              <?php if ($sub['children']): ?>
              <button class="mobile-sub d-flex w-100 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $sid ?>"><?= e($sub['name']) ?><i class="bi bi-chevron-down ms-auto"></i></button>
              <div class="collapse" id="<?= $sid ?>">
                <a class="mobile-child fw-semibold" href="<?= e(category_url($sub)) ?>">All <?= e($sub['name']) ?></a>
                <?php foreach ($sub['children'] as $child): ?>
                <a class="mobile-child" href="<?= e(category_url($child)) ?>"><?= e($child['name']) ?></a>
                <?php endforeach; ?>
              </div>
              <?php else: ?>
              <a class="mobile-sub" href="<?= e(category_url($sub)) ?>"><?= e($sub['name']) ?></a>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="p-3 border-top small">
      <a href="<?= url($customer ? 'my-account/' : 'login/') ?>" class="mobile-link px-0"><i class="bi bi-person me-2"></i><?= $customer ? 'My Account' : 'Login / Register' ?></a>
      <a href="<?= url('track-order/') ?>" class="mobile-link px-0"><i class="bi bi-truck me-2"></i>Track Order</a>
      <a href="<?= url('contact/') ?>" class="mobile-link px-0"><i class="bi bi-headset me-2"></i>Contact Us</a>
    </div>
  </div>
</div>

<!-- Mobile search overlay -->
<div class="modal fade" id="searchOverlay" tabindex="-1" aria-label="Search">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content">
      <div class="modal-body p-3">
        <form class="search-box mobile" action="<?= url('search/') ?>" method="get" role="search" autocomplete="off">
          <div class="d-flex gap-2 align-items-center">
            <button type="button" class="btn btn-icon" data-bs-dismiss="modal" aria-label="Close search"><i class="bi bi-arrow-left"></i></button>
            <input type="search" name="q" class="form-control form-control-lg js-search-input" placeholder="Search products…" aria-label="Search products" maxlength="100">
            <button class="btn btn-brand btn-lg" type="submit" aria-label="Search"><i class="bi bi-search"></i></button>
          </div>
          <div class="search-suggest static mt-3"></div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Delivery area picker -->
<div class="modal fade" id="zoneModal" tabindex="-1" aria-labelledby="zoneModalLabel">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header"><h2 class="modal-title h5" id="zoneModalLabel"><i class="bi bi-geo-alt text-brand me-2"></i>Select your delivery area</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <input type="search" class="form-control mb-3" id="zoneFilter" placeholder="Search area…" aria-label="Search area">
        <div class="list-group zone-list">
          <?php foreach (delivery_zones() as $z): ?>
          <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center js-zone<?= $zone && $zone['id'] == $z['id'] ? ' active' : '' ?>" data-id="<?= (int) $z['id'] ?>" data-label="<?= e($z['area'] . ', ' . $z['city']) ?>">
            <span><strong><?= e($z['area']) ?></strong><br><small class="text-muted"><?= e($z['city']) ?> · <?= e($z['estimated_time']) ?></small></span>
            <span class="small text-end">Delivery <?= money($z['delivery_fee']) ?><?php if ($z['free_delivery_over']): ?><br><small class="text-success">Free over <?= money($z['free_delivery_over']) ?></small><?php endif; ?></span>
          </button>
          <?php endforeach; ?>
        </div>
        <p class="small text-muted mt-3 mb-0"><i class="bi bi-shop me-1"></i>Prefer to collect? Choose <strong>Store Pickup</strong> at checkout.</p>
      </div>
    </div>
  </div>
</div>

<main id="main" class="site-main">
<?php if (!empty($_SESSION['_flash']) && !page_get('own_flashes')): ?><div class="container pt-3"><?= render_flashes() ?></div><?php endif; ?>
