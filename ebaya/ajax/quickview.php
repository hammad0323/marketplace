<?php
if (!defined('EBAYA')) { http_response_code(403); exit; }
$product = product_by_slug(preg_replace('/[^a-z0-9\-]/', '', (string)get('slug')));
if (!$product) json_out(['ok' => false, 'message' => 'Product not found.'], 404);
$images = product_images((int)$product['id']);
$matrix = product_variant_matrix($product);
$compact = true;
ob_start(); ?>
<div class="row g-0 quickview">
  <div class="col-md-6">
    <div class="swiper qv-swiper"><div class="swiper-wrapper">
      <?php foreach ($images ?: [['path' => null, 'alt_text' => '']] as $img): ?><div class="swiper-slide"><img src="<?= e(img_url($img['path'])) ?>" alt="<?= e($img['alt_text'] ?: $product['name']) ?>"></div><?php endforeach; ?>
    </div><div class="swiper-pagination"></div></div>
  </div>
  <div class="col-md-6"><div class="qv-info">
    <h2 class="pdp-title"><?= e($product['name']) ?></h2>
    <?php if ($product['short_description']): ?><p class="pdp-short"><?= e($product['short_description']) ?></p><?php endif; ?>
    <?php include ROOT_PATH . '/templates/product-buybox.php'; ?>
  </div></div>
</div>
<?php json_out(['ok' => true, 'html' => ob_get_clean()]);
