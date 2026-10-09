<?php
$products = new_arrival_products(max(1, min(24, (int) $s['count'])), $s['source'] ?? 'flagged', (array) ($s['product_ids'] ?? []));
if (!$products) {
    return;
}
?>
<section <?= section_attrs($s, 'section--products') ?>>
  <div class="<?= e(section_container_class($s)) ?>">
    <?php view('sections/_heading', ['s' => $s, 'ctaRight' => true]); ?>
    <div<?= reveal_attr($s, 100) ?>><?php partial('product-carousel', ['products' => $products, 'label' => $s['title']]); ?></div>
  </div>
</section>
