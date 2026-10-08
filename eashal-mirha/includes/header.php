<?php
/**
 * Storefront header. Pages set $seo (array) and optionally $bodyClass
 * before including this file.
 */
$seo = $seo ?? [];
$siteName  = setting('site_name', 'Eashal Mirha');
$metaTitle = !empty($seo['title']) ? $seo['title'] : setting('meta_title', $siteName);
$metaDesc  = $seo['description'] ?? setting('meta_description');
$metaKeys  = $seo['keywords'] ?? setting('meta_keywords');
$canonical = $seo['canonical'] ?? abs_url(ltrim(substr(strtok($_SERVER['REQUEST_URI'] ?? '/', '?'), strlen(base_path())), '/'));
$ogImage   = $seo['image'] ?? setting('og_image');
$ogImage   = $ogImage ? (preg_match('~^https?://~', $ogImage) ? $ogImage : site_url() . substr(img($ogImage), strlen(base_path()))) : '';
$favicon   = setting('favicon');
$logo      = setting('logo');
$cats      = category_tree(true);
$bodyClass = $bodyClass ?? '';
$wish      = wishlist_ids();
$c         = customer();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($metaTitle) ?></title>
<meta name="description" content="<?= e($metaDesc) ?>">
<?php if ($metaKeys): ?><meta name="keywords" content="<?= e($metaKeys) ?>"><?php endif; ?>
<link rel="canonical" href="<?= e($canonical) ?>">
<?php if (!empty($seo['noindex'])): ?><meta name="robots" content="noindex, nofollow"><?php else: ?><meta name="robots" content="index, follow, max-image-preview:large"><?php endif; ?>
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:type" content="<?= e($seo['type'] ?? 'website') ?>">
<meta property="og:title" content="<?= e($metaTitle) ?>">
<meta property="og:description" content="<?= e($metaDesc) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<?php if ($ogImage): ?><meta property="og:image" content="<?= e($ogImage) ?>"><meta name="twitter:image" content="<?= e($ogImage) ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($metaTitle) ?>">
<meta name="twitter:description" content="<?= e($metaDesc) ?>">
<?php if (setting('google_verification')): ?><meta name="google-site-verification" content="<?= e(setting('google_verification')) ?>"><?php endif; ?>
<meta name="theme-color" content="<?= e(setting('color_black', '#0b0b0b')) ?>">
<link rel="icon" href="<?= e($favicon ? img($favicon) : url('assets/images/favicon.svg')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('vendor/swiper/swiper-bundle.min.css') ?>">
<link rel="stylesheet" href="<?= asset('css/style.css') ?>">
<style>:root{--gold:<?= e(setting('color_gold', '#c9a24a')) ?>;--black:<?= e(setting('color_black', '#0b0b0b')) ?>;--cream:<?= e(setting('color_cream', '#f7f1e6')) ?>;--logo-h:<?= (int)setting('logo_height', 46) ?>px}</style>
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'Organization',
    'name'     => $siteName,
    'url'      => abs_url(''),
    'logo'     => $logo ? site_url() . substr(img($logo), strlen(base_path())) : null,
    'contactPoint' => ['@type' => 'ContactPoint', 'telephone' => setting('phone'), 'contactType' => 'customer service'],
    'sameAs'   => array_values(array_filter([setting('facebook'), setting('instagram'), setting('tiktok'), setting('youtube'), setting('pinterest')])),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php foreach (($seo['schema'] ?? []) as $schema): ?>
<script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php endforeach; ?>
<?php if (setting('ga_id')): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e(setting('ga_id')) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','<?= e(setting('ga_id')) ?>');</script>
<?php endif; ?>
<?php if (setting('fb_pixel')): ?>
<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','<?= e(setting('fb_pixel')) ?>');fbq('track','PageView');</script>
<?php endif; ?>
<?= setting('head_code') ?>
</head>
<body class="<?= e($bodyClass) ?>" data-base="<?= e(base_path()) ?>" data-csrf="<?= e(csrf_token()) ?>">
<?= setting('body_code') ?>
<?php if (setting('preloader', '1') === '1'): ?>
<div class="preloader" id="preloader">
  <div class="preloader__inner">
    <span class="preloader__mark"><?= e(mb_substr($siteName, 0, 1)) ?><?= e(mb_substr(strstr($siteName, ' ') ?: '', 1, 1)) ?></span>
    <span class="preloader__name"><?= e($siteName) ?></span>
    <span class="preloader__line"></span>
  </div>
</div>
<?php endif; ?>

<?php if (setting('announcement_enabled', '1') === '1' && setting('announcement')): ?>
<div class="announce"><div class="announce__track">
  <?php for ($i = 0; $i < 4; $i++): ?><span><?= e(setting('announcement')) ?></span><?php endfor; ?>
</div></div>
<?php endif; ?>

<header class="site-header" id="siteHeader">
  <div class="container header-row">
    <div class="header-left">
      <button class="icon-btn only-mobile" data-open="drawer" aria-label="Menu"><?= icon('menu', 22) ?></button>
      <button class="icon-btn" data-open="search" aria-label="Search"><?= icon('search', 20) ?></button>
      <?php if (setting('phone')): ?><a class="header-contact hide-mobile" href="tel:<?= e(preg_replace('~[^0-9+]~', '', setting('phone'))) ?>"><?= icon('phone', 16) ?> <?= e(setting('phone')) ?></a><?php endif; ?>
    </div>
    <a class="logo" href="<?= url('') ?>" aria-label="<?= e($siteName) ?> home">
      <?php if ($logo): ?>
        <img src="<?= e(img($logo)) ?>" alt="<?= e($siteName) ?>">
      <?php else: ?>
        <span class="logo__text"><?= e($siteName) ?></span>
        <span class="logo__tag"><?= e(setting('tagline')) ?></span>
      <?php endif; ?>
    </a>
    <div class="header-right">
      <a class="icon-btn hide-mobile" href="<?= url($c ? 'account' : 'login') ?>" aria-label="Account"><?= icon('user', 20) ?></a>
      <a class="icon-btn" href="<?= url('wishlist') ?>" aria-label="Wishlist"><?= icon('heart', 20) ?><span class="count" id="wishCount"<?= $wish ? '' : ' hidden' ?>><?= count($wish) ?></span></a>
      <button class="icon-btn" data-open="cart" aria-label="Shopping bag"><?= icon('bag', 20) ?><span class="count" id="cartCount"<?= cart_count() ? '' : ' hidden' ?>><?= cart_count() ?></span></button>
    </div>
  </div>
  <nav class="main-nav hide-mobile" aria-label="Main">
    <ul class="container">
      <li><a href="<?= url('shop?filter=new') ?>">New In</a></li>
      <?php foreach ($cats as $cat): ?>
        <li class="<?= $cat['children'] ? 'has-mega' : '' ?>">
          <a href="<?= category_url($cat) ?>"><?= e($cat['name']) ?></a>
          <?php if ($cat['children']): ?>
          <div class="mega">
            <div class="mega__inner container">
              <div class="mega__links">
                <h4><?= e($cat['name']) ?></h4>
                <a href="<?= category_url($cat) ?>">View All</a>
                <?php foreach ($cat['children'] as $sub): ?><a href="<?= category_url($sub) ?>"><?= e($sub['name']) ?></a><?php endforeach; ?>
              </div>
              <a class="mega__feature" href="<?= category_url($cat) ?>">
                <img src="<?= e(img($cat['banner'] ?: $cat['image'])) ?>" alt="<?= e($cat['name']) ?>" loading="lazy">
                <span><?= e($cat['description'] ? excerpt($cat['description'], 70) : 'Discover ' . $cat['name']) ?></span>
              </a>
            </div>
          </div>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
      <li><a href="<?= url('shop?filter=sale') ?>" class="nav-sale">Sale</a></li>
    </ul>
  </nav>
</header>

<!-- Search overlay -->
<div class="overlay-search" id="search" aria-hidden="true">
  <button class="icon-btn overlay-close" data-close aria-label="Close"><?= icon('close', 26) ?></button>
  <form action="<?= url('shop') ?>" method="get" class="overlay-search__form">
    <label for="q">What are you looking for?</label>
    <input id="q" type="search" name="q" placeholder="Search bridal, luxury pret, lawn…" autocomplete="off">
    <div class="search-suggest" id="searchSuggest"></div>
  </form>
</div>

<!-- Mobile drawer -->
<aside class="drawer drawer--left" id="drawer" aria-hidden="true">
  <div class="drawer__head"><span class="logo__text sm"><?= e($siteName) ?></span><button class="icon-btn" data-close aria-label="Close"><?= icon('close', 22) ?></button></div>
  <nav class="drawer__nav">
    <a href="<?= url('shop?filter=new') ?>">New In</a>
    <?php foreach ($cats as $cat): ?>
      <?php if ($cat['children']): ?>
        <details><summary><?= e($cat['name']) ?></summary>
          <a href="<?= category_url($cat) ?>">View All <?= e($cat['name']) ?></a>
          <?php foreach ($cat['children'] as $sub): ?><a href="<?= category_url($sub) ?>"><?= e($sub['name']) ?></a><?php endforeach; ?>
        </details>
      <?php else: ?>
        <a href="<?= category_url($cat) ?>"><?= e($cat['name']) ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
    <a href="<?= url('shop?filter=sale') ?>" class="nav-sale">Sale</a>
    <hr>
    <a href="<?= url($c ? 'account' : 'login') ?>"><?= $c ? 'My Account' : 'Login / Register' ?></a>
    <a href="<?= url('track-order') ?>">Track Order</a>
    <a href="<?= url('contact') ?>">Contact Us</a>
  </nav>
</aside>

<!-- Cart drawer -->
<aside class="drawer drawer--right" id="cart" aria-hidden="true">
  <div class="drawer__head"><span class="drawer__title">Your Bag</span><button class="icon-btn" data-close aria-label="Close"><?= icon('close', 22) ?></button></div>
  <div class="drawer__body" id="miniCart"><div class="mini-empty">Loading…</div></div>
</aside>
<div class="backdrop" id="backdrop"></div>
<div class="toasts" id="toasts"></div>

<main id="main">
<?php foreach (flashes() as $f): ?>
  <div class="container"><div class="alert alert-<?= e($f['type']) ?>" data-reveal><?= e($f['msg']) ?></div></div>
<?php endforeach; ?>
