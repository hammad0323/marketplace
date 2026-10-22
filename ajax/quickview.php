<?php
require __DIR__ . '/_init.php';
$product = db_row("SELECT * FROM products WHERE id = ? AND status = 'published'", [input_int('id')]);
if (!$product) {
    json_response(['ok' => false, 'message' => 'Product not found.'], 404);
}
$images = product_images((int) $product['id']);
$variants = product_variants((int) $product['id']);
$groups = variant_option_groups($variants);
ob_start();
?>
<div class="uc-qv">
    <div class="uc-qv-gallery">
        <div class="uc-qv-track">
            <?php foreach (array_slice($images, 0, 6) as $i => $im): ?>
                <img src="<?= e(img_sized($im['image'], 900)) ?>" alt="<?= e($im['alt_text'] ?: $product['name']) ?>"<?= $i ? ' loading="lazy"' : '' ?>>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="uc-qv-info">
        <h2 class="uc-pdp-title"><a href="<?= e(url('product/' . $product['slug'])) ?>"><?= e($product['name']) ?></a></h2>
        <?php if ((int) $product['rating_count']): ?><p><?= render_stars((float) $product['rating_avg']) ?> <small>(<?= (int) $product['rating_count'] ?>)</small></p><?php endif; ?>
        <?php if ($product['short_description']): ?><p class="uc-pdp-short"><?= e($product['short_description']) ?></p><?php endif; ?>
        <?php partial('components/product-buy', ['product' => $product, 'variants' => $variants, 'groups' => $groups, 'context' => 'quickview']); ?>
    </div>
</div>
<?php
json_response(['ok' => true, 'html' => ob_get_clean()]);
