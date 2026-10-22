<?php
seo_set(['title' => 'Wishlist', 'noindex' => true]);
$items = products_by_ids(array_reverse(wishlist_product_ids()), 100);
$bodyClass = 'page-wishlist';
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-container uc-page-pad">
    <?= breadcrumbs_html([['Home', url()], ['Wishlist', null]]) ?>
    <h1 class="uc-page-title">Your Wishlist</h1>
    <?php if (!current_customer() && $items): ?><p class="text-muted"><a href="<?= e(url('account/login')) ?>">Sign in</a> to save your wishlist across devices.</p><?php endif; ?>
    <?php if ($items): ?>
        <div class="uc-grid uc-grid--4" data-wishlist-page>
            <?php foreach ($items as $p) partial('components/product-card', ['p' => $p]); ?>
        </div>
    <?php else: ?>
        <div class="uc-empty"><i class="bi bi-heart"></i><h2>Your wishlist is empty</h2><p>Tap the heart on any product to save it here.</p><a class="uc-btn" href="<?= e(url('shop')) ?>">Start Shopping</a></div>
    <?php endif; ?>
</section>
<?php require UC_ROOT . '/includes/footer.php';
