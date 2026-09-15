<?php
$cartCount = 0;
if ($customer = current_customer()) {
    $cartRow = db_fetch_one("SELECT SUM(ci.quantity) qty FROM cart_items ci
        JOIN carts c ON c.id = ci.cart_id WHERE c.customer_id = ?", 'i', [$customer['id']]);
    $cartCount = (int)($cartRow['qty'] ?? 0);
}
$wishlistCount = 0;
if ($customer) {
    $wishlistCount = (int)(db_fetch_one("SELECT COUNT(*) c FROM wishlists WHERE customer_id=?", 'i', [$customer['id']])['c'] ?? 0);
}
$navCategories = db_fetch_all("SELECT id, name, slug FROM categories WHERE status='active' ORDER BY sort_order");
?>
<header class="site-header">
  <div class="topbar">
    <div class="container topbar-inner">
      <div class="topbar-left">
        <a href="<?= base_url('search.php') ?>">Hot Deals</a>
        <?php if ($customer): ?>
          <a href="<?= customer_url('dashboard.php') ?>">My Account</a>
        <?php else: ?>
          <a href="<?= base_url('login.php') ?>">My Account</a>
        <?php endif; ?>
        <a href="<?= base_url('wishlist.php') ?>">Wishlist</a>
        <a href="<?= customer_url('orders.php') ?>">Order Tracking</a>
      </div>
      <div class="topbar-right">
        <span><i class="fa-solid fa-headset"></i> Need help? Call us: <strong><?= clean(get_setting('site_phone')) ?></strong></span>
        <a href="<?= shop_url('register.php') ?>">Sell on <?= clean(site_name()) ?></a>
      </div>
    </div>
  </div>

  <div class="container header-main">
    <a class="logo" href="<?= base_url() ?>"><i class="fa-solid fa-basket-shopping"></i> <?= clean(site_name()) ?></a>

    <form class="search-bar" action="<?= base_url('search.php') ?>" method="get">
      <select name="category_id" class="search-category-select" aria-label="Category">
        <option value="">All Categories</option>
        <?php foreach ($navCategories as $cat): ?>
          <option value="<?= $cat['id'] ?>"><?= clean($cat['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="q" placeholder="Search for products, shops, brands..." value="<?= clean($_GET['q'] ?? '') ?>">
      <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> <span>Search</span></button>
    </form>

    <div class="header-actions">
      <a href="<?= base_url('wishlist.php') ?>" class="header-action-item">
        <span class="header-action-icon"><i class="fa-regular fa-heart"></i><?php if ($wishlistCount): ?><span class="mini-badge"><?= $wishlistCount ?></span><?php endif; ?></span>
        <span class="header-action-label">Wishlist</span>
      </a>
      <a href="<?= base_url('cart.php') ?>" class="header-action-item">
        <span class="header-action-icon"><i class="fa-solid fa-cart-shopping"></i><span class="mini-badge" id="cart-badge"<?= $cartCount ? '' : ' style="display:none"' ?>><?= $cartCount ?></span></span>
        <span class="header-action-label">Cart</span>
      </a>
      <?php if ($customer): ?>
        <a href="<?= customer_url('dashboard.php') ?>" class="header-action-item">
          <span class="header-action-icon"><i class="fa-regular fa-user"></i></span>
          <span class="header-action-label"><?= clean($customer['first_name']) ?></span>
        </a>
      <?php else: ?>
        <a href="<?= base_url('login.php') ?>" class="header-action-item">
          <span class="header-action-icon"><i class="fa-regular fa-user"></i></span>
          <span class="header-action-label">Account</span>
        </a>
      <?php endif; ?>
      <button class="mobile-menu-toggle" id="mobile-menu-toggle" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
    </div>
  </div>

  <nav class="category-nav" id="category-nav">
    <div class="container category-nav-inner">
      <button class="browse-categories-btn" id="browse-categories-btn" type="button">
        <i class="fa-solid fa-bars"></i> Browse All Categories <i class="fa-solid fa-chevron-down browse-caret"></i>
      </button>
      <div class="category-dropdown" id="category-dropdown">
        <?php foreach ($navCategories as $cat): ?>
          <a href="<?= base_url('category.php?slug=' . $cat['slug']) ?>"><i class="fa-solid fa-angle-right"></i> <?= clean($cat['name']) ?></a>
        <?php endforeach; ?>
      </div>
      <div class="category-nav-links">
        <a href="<?= base_url() ?>">Home</a>
        <a href="<?= base_url('search.php') ?>">Hot Deals</a>
        <?php foreach (array_slice($navCategories, 0, 6) as $cat): ?>
          <a href="<?= base_url('category.php?slug=' . $cat['slug']) ?>"><?= clean($cat['name']) ?></a>
        <?php endforeach; ?>
        <a href="<?= shop_url('register.php') ?>">Become a Vendor</a>
      </div>
    </div>
  </nav>
</header>
