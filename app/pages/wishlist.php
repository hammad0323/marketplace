<?php
if (!wishlist_enabled()) {
    not_found();
}
meta_set(['title' => 'Wishlist', 'noindex' => true]);
$items = products_by_ids(wishlist_product_ids());
partial('header');
?>
<div class="container container--wide page-pad">
  <p class="eyebrow">Saved for later</p>
  <h1 class="page-title">Your Wishlist</h1>
  <?php if ($items): ?>
    <div class="product-grid"><?php foreach ($items as $p) { partial('product-card', ['p' => $p]); } ?></div>
    <?php if (!current_customer()): ?><p class="text-muted mt-4"><a href="<?= e(path_url('account/login')) ?>">Sign in</a> to keep your wishlist across devices.</p><?php endif; ?>
  <?php else: ?>
    <div class="empty-state"><i class="bi bi-heart"></i><h2>Nothing saved yet</h2><p>Tap the heart on any piece to keep it here.</p><a class="btn-lux" href="<?= e(path_url('shop')) ?>">Explore the collection</a></div>
  <?php endif; ?>
</div>
<?php partial('footer');
