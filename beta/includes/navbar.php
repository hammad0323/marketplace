<?php
$cartCount = 0;
if ($customer = current_customer()) {
    $cartRow = db_fetch_one("SELECT SUM(ci.quantity) qty FROM cart_items ci
        JOIN carts c ON c.id = ci.cart_id WHERE c.customer_id = ?", 'i', [$customer['id']]);
    $cartCount = (int)($cartRow['qty'] ?? 0);
}
$navCategories = db_fetch_all("SELECT id, name, slug FROM categories WHERE status='active' ORDER BY sort_order LIMIT 10");
?>
<header class="site-header">
  <div class="topbar">
    <div class="container topbar-inner">
      <span><?= clean(get_setting('site_phone')) ?> &nbsp;|&nbsp; <?= clean(get_setting('site_email')) ?></span>
      <span><a href="<?= shop_url('register.php') ?>">Sell on <?= clean(site_name()) ?></a></span>
    </div>
  </div>
  <div class="container header-main">
    <a class="logo" href="<?= base_url() ?>"><?= clean(site_name()) ?></a>
    <form class="search-bar" action="<?= base_url('search.php') ?>" method="get">
      <input type="text" name="q" placeholder="Search products, shops, categories..." value="<?= clean($_GET['q'] ?? '') ?>">
      <button type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
    </form>
    <div class="header-actions">
      <a href="<?= base_url('wishlist.php') ?>" title="Wishlist"><i class="fa-regular fa-heart"></i></a>
      <a href="<?= base_url('cart.php') ?>" title="Cart" class="cart-link">
        <i class="fa-solid fa-cart-shopping"></i>
        <span class="cart-badge" id="cart-badge"><?= $cartCount ?></span>
      </a>
      <?php if ($customer): ?>
        <div class="dropdown">
          <a href="<?= customer_url('dashboard.php') ?>"><i class="fa-regular fa-user"></i> <?= clean($customer['first_name']) ?></a>
        </div>
      <?php else: ?>
        <a href="<?= base_url('login.php') ?>"><i class="fa-regular fa-user"></i> Login</a>
      <?php endif; ?>
      <button class="mobile-menu-toggle" id="mobile-menu-toggle" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
    </div>
  </div>
  <nav class="category-nav" id="category-nav">
    <div class="container category-nav-inner">
      <a href="<?= base_url() ?>">Home</a>
      <?php foreach ($navCategories as $cat): ?>
        <a href="<?= base_url('category.php?slug=' . $cat['slug']) ?>"><?= clean($cat['name']) ?></a>
      <?php endforeach; ?>
    </div>
  </nav>
</header>
