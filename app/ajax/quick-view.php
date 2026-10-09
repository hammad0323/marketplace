<?php
$p = product_by_slug(input('slug', '', 'get'));
if (!$p) {
    json_response(['ok' => false, 'message' => 'Product not found.'], 404);
}
$images = product_images((int) $p['id']);
$variants = product_variants((int) $p['id']);
ob_start(); ?>
<div class="quickview__grid">
  <div class="quickview__gallery"><?php partial('product-gallery', ['images' => $images, 'p' => $p]); ?></div>
  <div class="quickview__info"><?php partial('product-buybox', ['p' => $p, 'variants' => $variants, 'images' => $images, 'compact' => true, 'rating' => product_rating_summary((int) $p['id'])]); ?></div>
</div>
<?php
json_response(['ok' => true, 'html' => ob_get_clean()]);
