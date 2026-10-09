<?php
$siteName = setting('site_name', 'Beglet');
$title = page_title();
$desc = page_description();
$canonical = canonical_url();
$ogImage = meta('image') ? abs_media_url(meta('image')) : abs_media_url(setting('og_default_image', 'assets/img/brand/og-default.jpg'));
$isHome = ($GLOBALS['route_path'] ?? '') === '';
$headerLayout = theme('theme_header_layout');
$menu = setting_json('main_menu', []);
$tree = category_tree();
$announcements = announcement_messages();
$cartCount = cart_count();
$customer = current_customer();
$bodyClasses = trim(($GLOBALS['body_class'] ?? '') . ' card-style-' . theme('theme_card_style') . ' header-' . $headerLayout . ' mobile-cols-' . theme('theme_mobile_columns') . ($isHome && homepage_has_hero() ? ' has-hero' : '') . (setting_bool('theme_animations', true) ? '' : ' no-anim'));
?><!doctype html>
<html lang="<?= e(setting('default_language', 'en')) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="robots" content="<?= e(robots_meta()) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<?php if (meta('prev')): ?><link rel="prev" href="<?= e(meta('prev')) ?>"><?php endif; ?>
<?php if (meta('next')): ?><link rel="next" href="<?= e(meta('next')) ?>"><?php endif; ?>
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:type" content="<?= e(meta('type', 'website')) ?>">
<meta property="og:title" content="<?= e(meta('og_title') ?: $title) ?>">
<meta property="og:description" content="<?= e(meta('og_description') ?: $desc) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<?php if ($ogImage): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<?php if (setting('twitter_handle')): ?><meta name="twitter:site" content="<?= e(setting('twitter_handle')) ?>"><?php endif; ?>
<meta name="twitter:title" content="<?= e(meta('og_title') ?: $title) ?>">
<meta name="twitter:description" content="<?= e(meta('og_description') ?: $desc) ?>">
<?php if ($ogImage): ?><meta name="twitter:image" content="<?= e($ogImage) ?>"><?php endif; ?>
<?php if (setting('google_site_verification')): ?><meta name="google-site-verification" content="<?= e(setting('google_site_verification')) ?>"><?php endif; ?>
<?php if (setting('bing_site_verification')): ?><meta name="msvalidate.01" content="<?= e(setting('bing_site_verification')) ?>"><?php endif; ?>
<meta name="theme-color" content="<?= e(theme('theme_header_bg')) ?>">
<link rel="icon" href="<?= e(media_url(setting('favicon_path', 'assets/img/brand/favicon.svg'))) ?>">
<link rel="preload" href="<?= e(path_url('assets/vendor/fonts/cormorant-garamond-latin-500-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/fonts/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/sweetalert2/sweetalert2.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<style><?= theme_css_vars() ?></style>
<?php
$jsonld = meta('jsonld', []);
if ($isHome) {
    $jsonld[] = organization_jsonld();
    $jsonld[] = website_jsonld();
}
if (meta('breadcrumbs')) {
    $jsonld[] = breadcrumbs_jsonld(meta('breadcrumbs'));
}
foreach ($jsonld as $ld): ?>
<script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endforeach; ?>
<?php if (preg_match('/^G-[A-Z0-9]{4,15}$/', (string) setting('ga4_measurement_id'))): $ga = setting('ga4_measurement_id'); ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= e($ga) ?>');</script>
<?php endif; ?>
<?php if (!homepage_preview_mode()) { echo setting('head_scripts', ''); } ?>
</head>
<body class="<?= e($bodyClasses) ?>">
<?php if (!homepage_preview_mode()) { echo setting('body_scripts', ''); } ?>
<a class="skip-link" href="#main">Skip to content</a>
<?php if (homepage_preview_mode()): ?>
<div class="preview-bar">Previewing unpublished homepage changes · <a href="<?= e(admin_url('homepage')) ?>">Back to builder</a></div>
<?php endif; ?>

<?php if ($announcements): ?>
<div class="announcement" style="<?= valid_hex(setting('announcement_bg', '')) ? 'background:' . e(setting('announcement_bg')) . ';' : '' ?><?= valid_hex(setting('announcement_color', '')) ? 'color:' . e(setting('announcement_color')) . ';' : '' ?>" data-interval="<?= (int) setting('announcement_interval', '5000') ?>">
  <div class="announcement__track" aria-live="polite">
    <?php foreach ($announcements as $i => $m): ?>
      <div class="announcement__item<?= $i === 0 ? ' is-active' : '' ?>">
        <?php if (!empty($m['url'])): ?><a href="<?= e(safe_link($m['url'])) ?>"><?= e($m['text']) ?></a><?php else: ?><span><?= e($m['text']) ?></span><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<header class="site-header<?= $isHome && homepage_has_hero() ? ' site-header--overlay' : '' ?>" id="siteHeader">
  <div class="container container--wide site-header__inner">
    <button class="icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileNav" aria-controls="mobileNav" aria-label="Open menu">
      <i class="bi bi-list"></i>
    </button>

    <a class="brand" href="<?= e(path_url('/')) ?>" aria-label="<?= e($siteName) ?> home">
      <?php if (setting('logo_path')): ?>
        <img src="<?= e(media_url(setting('logo_path'))) ?>" alt="<?= e($siteName) ?>" width="150" height="40">
      <?php else: ?>
        <span class="brand__word"><?= e(strtoupper(setting('brand_name', 'Beglet'))) ?></span>
        <span class="brand__tag"><?= e(setting('site_tagline', 'Crafted Leather')) ?></span>
      <?php endif; ?>
    </a>

    <nav class="main-nav d-none d-lg-block" aria-label="Main">
      <ul class="main-nav__list">
        <?php foreach ($menu as $item): ?>
          <?php if (!empty($item['mega'])): ?>
            <li class="main-nav__item has-mega">
              <a href="<?= e(safe_link($item['url'] ?? '/shop')) ?>" class="main-nav__link" aria-haspopup="true"><?= e($item['label']) ?> <i class="bi bi-chevron-down small"></i></a>
              <div class="mega" role="menu">
                <div class="container container--wide mega__inner">
                  <div class="mega__cols">
                    <?php foreach ($tree as $cat): ?>
                      <div class="mega__col">
                        <a class="mega__title" href="<?= e(category_url($cat)) ?>"><?= e($cat['name']) ?></a>
                        <?php if ($cat['children']): ?>
                          <ul>
                            <?php foreach ($cat['children'] as $child): ?>
                              <li><a href="<?= e(category_url($child)) ?>"><?= e($child['name']) ?></a></li>
                            <?php endforeach; ?>
                          </ul>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <?php $megaImg = setting('mega_menu_image', ''); if ($megaImg): ?>
                    <a class="mega__feature" href="<?= e(safe_link(setting('mega_menu_link', '/shop'))) ?>">
                      <img src="<?= e(media_url($megaImg)) ?>" alt="<?= e(setting('mega_menu_caption', 'Shop the collection')) ?>" loading="lazy">
                      <span><?= e(setting('mega_menu_caption', 'Shop the collection')) ?> <i class="bi bi-arrow-right"></i></span>
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            </li>
          <?php else: ?>
            <li class="main-nav__item"><a href="<?= e(safe_link($item['url'] ?? '#')) ?>" class="main-nav__link"><?= e($item['label']) ?></a></li>
          <?php endif; ?>
        <?php endforeach; ?>
      </ul>
    </nav>

    <div class="header-icons">
      <button class="icon-btn" type="button" data-search-open aria-label="Search"><i class="bi bi-search"></i></button>
      <a class="icon-btn d-none d-sm-inline-flex" href="<?= e(path_url($customer ? 'account' : 'account/login')) ?>" aria-label="<?= $customer ? 'My account' : 'Sign in' ?>"><i class="bi bi-person"></i></a>
      <?php if (wishlist_enabled()): ?>
        <a class="icon-btn d-none d-sm-inline-flex" href="<?= e(path_url('wishlist')) ?>" aria-label="Wishlist"><i class="bi bi-heart"></i><span class="icon-badge" data-wishlist-count <?= wishlist_count() ? '' : 'hidden' ?>><?= wishlist_count() ?></span></a>
      <?php endif; ?>
      <a class="icon-btn" href="<?= e(path_url('cart')) ?>" data-minicart-open aria-label="Shopping bag"><i class="bi bi-bag"></i><span class="icon-badge" data-cart-count <?= $cartCount ? '' : 'hidden' ?>><?= $cartCount ?></span></a>
    </div>
  </div>
</header>

<!-- Search overlay -->
<div class="search-overlay" id="searchOverlay" aria-hidden="true" role="dialog" aria-label="Search">
  <div class="container container--narrow">
    <form class="search-overlay__form" action="<?= e(path_url('search')) ?>" method="get" role="search">
      <i class="bi bi-search"></i>
      <input type="search" name="q" placeholder="Search wallets, card holders, accessories…" autocomplete="off" aria-label="Search products" data-search-input>
      <button type="button" class="icon-btn" data-search-close aria-label="Close search"><i class="bi bi-x-lg"></i></button>
    </form>
    <div class="search-suggest" data-search-results></div>
    <?php $popular = array_filter(array_map('trim', explode(',', (string) setting('popular_searches', '')))); if ($popular): ?>
      <div class="search-popular"><span>Popular:</span>
        <?php foreach ($popular as $term): ?><a href="<?= e(path_url('search', ['q' => $term])) ?>"><?= e($term) ?></a><?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Mobile navigation -->
<div class="offcanvas offcanvas-start mobile-nav" tabindex="-1" id="mobileNav" aria-labelledby="mobileNavLabel">
  <div class="offcanvas-header">
    <span class="brand__word" id="mobileNavLabel"><?= e(strtoupper(setting('brand_name', 'Beglet'))) ?></span>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <form action="<?= e(path_url('search')) ?>" method="get" class="mobile-nav__search" role="search">
      <input type="search" name="q" placeholder="Search" aria-label="Search">
      <button aria-label="Search"><i class="bi bi-search"></i></button>
    </form>
    <ul class="mobile-nav__list">
      <?php foreach ($tree as $i => $cat): ?>
        <li>
          <?php if ($cat['children']): ?>
            <button class="mobile-nav__toggle collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#mcat<?= (int) $cat['id'] ?>" aria-expanded="false"><?= e($cat['name']) ?><i class="bi bi-plus"></i></button>
            <ul class="collapse mobile-nav__sub" id="mcat<?= (int) $cat['id'] ?>">
              <li><a href="<?= e(category_url($cat)) ?>">All <?= e($cat['name']) ?></a></li>
              <?php foreach ($cat['children'] as $child): ?><li><a href="<?= e(category_url($child)) ?>"><?= e($child['name']) ?></a></li><?php endforeach; ?>
            </ul>
          <?php else: ?>
            <a href="<?= e(category_url($cat)) ?>"><?= e($cat['name']) ?></a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
      <?php foreach ($menu as $item): if (!empty($item['mega'])) continue; ?>
        <li><a href="<?= e(safe_link($item['url'] ?? '#')) ?>"><?= e($item['label']) ?></a></li>
      <?php endforeach; ?>
    </ul>
    <div class="mobile-nav__footer">
      <a href="<?= e(path_url($customer ? 'account' : 'account/login')) ?>"><i class="bi bi-person"></i> <?= $customer ? 'My account' : 'Sign in / Register' ?></a>
      <?php if (wishlist_enabled()): ?><a href="<?= e(path_url('wishlist')) ?>"><i class="bi bi-heart"></i> Wishlist</a><?php endif; ?>
      <a href="<?= e(path_url('track-order')) ?>"><i class="bi bi-truck"></i> Track an order</a>
      <?php if (setting('contact_phone')): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', setting('contact_phone'))) ?>"><i class="bi bi-telephone"></i> <?= e(setting('contact_phone')) ?></a><?php endif; ?>
    </div>
  </div>
</div>

<!-- Mini cart drawer -->
<div class="offcanvas offcanvas-end minicart" tabindex="-1" id="miniCart" aria-labelledby="miniCartLabel">
  <div class="offcanvas-header">
    <h2 class="offcanvas-title h5" id="miniCartLabel">Your Bag</h2>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body" data-minicart-body>
    <div class="skeleton-list"><div class="skeleton"></div><div class="skeleton"></div></div>
  </div>
</div>

<?php foreach (take_flashes() as $f): ?>
  <div class="flash-data" hidden data-type="<?= e($f['type']) ?>" data-message="<?= e($f['message']) ?>"></div>
<?php endforeach; ?>

<main id="main" class="site-main">
