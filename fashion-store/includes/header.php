<?php
require_once __DIR__ . '/functions.php';
$__cats = category_tree($mysqli);
$__cartCount = cart_count();
$__customer = current_customer();
$pageTitle = $pageTitle ?? get_setting('seo_default_title', get_setting('store_name'));
$pageDescription = $pageDescription ?? get_setting('seo_default_description');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDescription) ?>">
<link rel="icon" href="<?= BASE_URL ?>/assets/img/placeholder.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<div class="announce-bar">
  <div class="container text-center">Free shipping on orders above <?= format_price(get_setting('free_shipping_threshold', 5000)) ?> &nbsp;|&nbsp; New collection now live</div>
</div>

<header class="site-header">
  <div class="container">
    <div class="header-row">
      <button class="mobile-toggle d-lg-none" id="mobileMenuToggle"><i class="bi bi-list"></i></button>
      <a href="<?= BASE_URL ?>/index.php" class="brand"><?= e(get_setting('store_name', 'Fashion Store')) ?></a>
      <nav class="main-nav d-none d-lg-flex">
        <?php foreach ($__cats as $cat): ?>
          <div class="nav-item-drop">
            <a href="<?= BASE_URL ?>/shop.php?category=<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></a>
            <?php if ($cat['children']): ?>
            <div class="mega-menu">
              <?php foreach ($cat['children'] as $sub): ?>
                <a href="<?= BASE_URL ?>/shop.php?category=<?= e($sub['slug']) ?>"><?= e($sub['name']) ?></a>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <a href="<?= BASE_URL ?>/shop.php?sale=1" class="text-danger">Sale</a>
        <a href="<?= BASE_URL ?>/blog.php">Journal</a>
      </nav>
      <div class="header-actions">
        <form class="search-form d-none d-md-flex" action="<?= BASE_URL ?>/search.php" method="get">
          <input type="text" name="q" id="searchInput" placeholder="Search products..." autocomplete="off">
          <button type="submit"><i class="bi bi-search"></i></button>
          <div id="searchSuggest" class="search-suggest"></div>
        </form>
        <a href="<?= BASE_URL ?>/search.php" class="icon-link d-md-none"><i class="bi bi-search"></i></a>
        <a href="<?= BASE_URL ?>/<?= $__customer ? 'account/dashboard.php' : 'login.php' ?>" class="icon-link"><i class="bi bi-person"></i></a>
        <a href="<?= BASE_URL ?>/account/wishlist.php" class="icon-link"><i class="bi bi-heart"></i></a>
        <a href="<?= BASE_URL ?>/cart.php" class="icon-link cart-link"><i class="bi bi-bag"></i> <span class="cart-count"><?= (int)$__cartCount ?></span></a>
      </div>
    </div>
  </div>
</header>

<div class="mobile-nav" id="mobileNav">
  <div class="mobile-nav-inner">
    <button class="mobile-close" id="mobileMenuClose"><i class="bi bi-x-lg"></i></button>
    <?php foreach ($__cats as $cat): ?>
      <a href="<?= BASE_URL ?>/shop.php?category=<?= e($cat['slug']) ?>" class="mobile-nav-link"><?= e($cat['name']) ?></a>
      <?php foreach ($cat['children'] as $sub): ?>
        <a href="<?= BASE_URL ?>/shop.php?category=<?= e($sub['slug']) ?>" class="mobile-nav-sublink">&mdash; <?= e($sub['name']) ?></a>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <a href="<?= BASE_URL ?>/blog.php" class="mobile-nav-link">Journal</a>
    <a href="<?= BASE_URL ?>/<?= $__customer ? 'account/dashboard.php' : 'login.php' ?>" class="mobile-nav-link">My Account</a>
  </div>
</div>

<?php foreach (flash_get_all() as $f): ?>
  <div class="container mt-3"><div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show"><?= e($f['message']) ?><button class="btn-close" data-bs-dismiss="alert"></button></div></div>
<?php endforeach; ?>
