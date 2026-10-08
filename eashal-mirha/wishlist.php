<?php
require __DIR__ . '/includes/bootstrap.php';

$ids = wishlist_ids();
$products = $ids ? product_list('p.id IN (' . implode(',', array_map('intval', $ids)) . ')', [], 'p.name', 100) : [];
$wish = $ids;
$seo = ['title' => 'Wishlist | ' . setting('site_name'), 'noindex' => true];
require ROOT . '/includes/header.php';
?>
<section class="page-title"><div class="container"><span class="ornament">✦</span><h1 class="section-title">Your Wishlist</h1></div></section>
<section class="section section--tight">
  <div class="container">
    <?php if ($products): ?>
      <div class="grid grid-4"><?php foreach ($products as $p) { require ROOT . '/includes/product-card.php'; } ?></div>
      <?php if (!customer()): ?><p class="muted center mt-40"><a class="link-underline" href="<?= url('login') ?>">Log in</a> to save your wishlist to your account.</p><?php endif; ?>
    <?php else: ?>
      <div class="empty-state" data-reveal><?= icon('heart', 48) ?><h3>Your wishlist is empty</h3><p>Tap the heart on any piece to save it for later.</p><a class="btn btn-dark" href="<?= url('shop') ?>">Explore the Collection</a></div>
    <?php endif; ?>
  </div>
</section>
<?php require ROOT . '/includes/footer.php';
