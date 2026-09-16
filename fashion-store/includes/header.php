<?php
require_once __DIR__ . '/functions.php';
$__cats = category_tree($mysqli);
$__cartCount = cart_count();
$__customer = current_customer();
$pageTitle = $pageTitle ?? get_setting('seo_default_title', get_setting('store_name'));
$pageDescription = $pageDescription ?? get_setting('seo_default_description');
$__logo = get_setting('site_logo');
$__favicon = get_setting('site_favicon');
$__accent = get_setting('theme_accent_color', '#a5763f');
$__ink = get_setting('theme_dark_color', '#211d17');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php render_seo_head(); ?>
<link rel="icon" href="<?= e($__favicon ? BASE_URL . '/' . $__favicon : BASE_URL . '/assets/img/placeholder.svg') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<style>
:root{
  --clr-accent:<?= e($__accent) ?>;
  --clr-accent-dark:<?= e($__accent) ?>;
  --clr-ink:<?= e($__ink) ?>;
}
</style>
</head>
<body>

<div class="announce-bar">
  <div class="container text-center">Free shipping on orders above <?= format_price(get_setting('free_shipping_threshold', 5000)) ?> &nbsp;|&nbsp; New collection now live</div>
</div>

<header class="site-header">
  <div class="container">
    <div class="header-row">
      <button class="mobile-toggle d-lg-none" id="mobileMenuToggle"><i class="bi bi-list"></i></button>
      <a href="<?= url() ?>" class="brand">
        <?php if ($__logo): ?>
          <img src="<?= e(BASE_URL . '/' . $__logo) ?>" alt="<?= e(get_setting('store_name', 'Fashion Store')) ?>" class="brand-logo">
        <?php else: ?>
          <?= e(get_setting('store_name', 'Fashion Store')) ?>
        <?php endif; ?>
      </a>
      <nav class="main-nav d-none d-lg-flex">
        <?php foreach ($__cats as $cat): ?>
          <div class="nav-item-drop">
            <a href="<?= e(category_url($cat['slug'])) ?>"><?= e($cat['name']) ?></a>
            <?php if ($cat['children']): ?>
            <div class="mega-menu">
              <?php foreach ($cat['children'] as $sub): ?>
                <a href="<?= e(category_url($sub['slug'])) ?>"><?= e($sub['name']) ?></a>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <a href="<?= e(url('shop', ['sale' => 1])) ?>" class="text-danger">Sale</a>
        <a href="<?= url('blog') ?>">Journal</a>
      </nav>
      <div class="header-actions">
        <form class="search-form d-none d-md-flex" action="<?= url('search') ?>" method="get">
          <input type="text" name="q" id="searchInput" placeholder="Search products..." autocomplete="off">
          <button type="submit"><i class="bi bi-search"></i></button>
          <div id="searchSuggest" class="search-suggest"></div>
        </form>
        <a href="<?= url('search') ?>" class="icon-link d-md-none"><i class="bi bi-search"></i></a>
        <a href="<?= url($__customer ? 'account/dashboard' : 'login') ?>" class="icon-link"><i class="bi bi-person"></i></a>
        <a href="<?= url('account/wishlist') ?>" class="icon-link"><i class="bi bi-heart"></i></a>
        <a href="<?= url('cart') ?>" class="icon-link cart-link"><i class="bi bi-bag"></i> <span class="cart-count"><?= (int)$__cartCount ?></span></a>
      </div>
    </div>
  </div>
</header>

<div class="mobile-nav" id="mobileNav">
  <div class="mobile-nav-inner">
    <button class="mobile-close" id="mobileMenuClose"><i class="bi bi-x-lg"></i></button>
    <?php foreach ($__cats as $cat): ?>
      <a href="<?= e(category_url($cat['slug'])) ?>" class="mobile-nav-link"><?= e($cat['name']) ?></a>
      <?php foreach ($cat['children'] as $sub): ?>
        <a href="<?= e(category_url($sub['slug'])) ?>" class="mobile-nav-sublink">&mdash; <?= e($sub['name']) ?></a>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <a href="<?= url('blog') ?>" class="mobile-nav-link">Journal</a>
    <a href="<?= url($__customer ? 'account/dashboard' : 'login') ?>" class="mobile-nav-link">My Account</a>
  </div>
</div>

<?php foreach (flash_get_all() as $f): ?>
  <div class="container mt-3"><div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show"><?= e($f['message']) ?><button class="btn-close" data-bs-dismiss="alert"></button></div></div>
<?php endforeach; ?>
