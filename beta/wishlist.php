<?php
require __DIR__ . '/config/config.php';
require_customer();
$customer = current_customer();
$items = db_fetch_all("SELECT p.*, s.shop_name, s.slug as shop_slug FROM wishlists w
    JOIN products p ON p.id = w.product_id JOIN shops s ON s.id = p.shop_id
    WHERE w.customer_id = ? ORDER BY w.created_at DESC", 'i', [$customer['id']]);
$pageTitle = 'Wishlist';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/product-card.php';
?>
<div class="container">
  <h1 class="page-title">My Wishlist</h1>
  <?php if ($items): ?>
    <div class="product-grid"><?php foreach ($items as $p) render_product_card($p); ?></div>
  <?php else: ?>
    <div class="empty-state">
      <i class="fa-regular fa-heart"></i>
      <h3>Your wishlist is empty</h3>
      <a class="btn btn-primary" href="<?= base_url() ?>">Browse Products</a>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
