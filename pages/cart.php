<?php
/** Shopping bag page. */
seo_set(['title' => 'Your Bag', 'noindex' => true]);
$cross = [];
$lines = cart_lines();
if ($lines) {
    $ids = array_column($lines, 'product_id');
    $rel = db_col('SELECT DISTINCT related_id FROM product_relations WHERE product_id IN (' . db_in($ids) . ") AND relation_type IN ('cross_sell','related') AND related_id NOT IN (" . db_in($ids) . ') LIMIT 8', array_merge($ids, $ids));
    $cross = products_by_ids($rel, 8);
}
$bodyClass = 'page-cart';
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-container uc-page-pad">
    <?= breadcrumbs_html([['Home', url()], ['Bag', null]]) ?>
    <h1 class="uc-page-title">Your Bag</h1>
    <div class="uc-cart-page" data-cart-page>
        <?php partial('components/cart-contents', ['variant' => 'page']); ?>
    </div>
</section>
<?php if ($cross): ?>
<section class="uc-section pt-0">
    <div class="uc-container">
        <header class="uc-section-head text-left"><p class="uc-eyebrow">Complete the look</p><h2 class="uc-section-title">Pairs Well With</h2></header>
        <?php partial('components/product-carousel', ['products' => $cross]); ?>
    </div>
</section>
<?php endif; ?>
<?php require UC_ROOT . '/includes/footer.php';
