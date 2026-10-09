<?php
if (!defined('EBAYA')) { http_response_code(403); exit; }
$ids = wishlist_ids();
$products = [];
if ($ids) [$products] = products_query(['ids' => array_reverse($ids), 'sort' => 'manual', 'limit' => 100]);
seo_set(['title' => 'Wishlist', 'noindex' => true]);
require ROOT_PATH . '/templates/header.php';
?>
<section class="page-section">
  <div class="container-eb">
    <h1 class="page-title text-center">Your Wishlist</h1>
    <?php if (!customer_id() && $products): ?><p class="text-center text-muted small"><a href="<?= e(url('account/login?return=' . urlencode(url('wishlist')))) ?>">Sign in</a> to save your wishlist across devices.</p><?php endif; ?>
    <?php if ($products): ?>
      <div class="product-grid"><?php foreach ($products as $p): ?><div data-reveal><?php include ROOT_PATH . '/templates/product-card.php'; ?></div><?php endforeach; ?></div>
    <?php else: ?>
      <div class="empty-state"><i class="bi bi-heart"></i><h2>Nothing saved yet</h2><p>Tap the heart on any piece to keep it here.</p><a class="btn btn-eb btn-primary-eb" href="<?= e(url('shop')) ?>">Explore abayas</a></div>
    <?php endif; ?>
  </div>
</section>
<?php require ROOT_PATH . '/templates/footer.php';
