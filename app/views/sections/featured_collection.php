<?php
$products = [];
$link = '';
$name = '';
if (($s['source_type'] ?? 'collection') === 'collection' && !empty($s['collection_id'])) {
    $col = db_one('SELECT * FROM collections WHERE id = ? AND is_active = 1', [(int) $s['collection_id']]);
    if ($col) {
        $res = products_query(['collection_id' => (int) $col['id'], 'per_page' => max(1, min(8, (int) $s['product_count']))]);
        $products = $res['items'];
        $link = path_url('collection/' . $col['slug']);
        $name = $col['name'];
        $s['image'] = $s['image'] ?: $col['image'];
        $s['description'] = $s['description'] ?: $col['description'];
    }
} elseif (!empty($s['category_id'])) {
    $cat = category_by_id((int) $s['category_id']);
    if ($cat && (int) $cat['is_active']) {
        $res = products_query(['category_ids' => category_descendant_ids((int) $cat['id']), 'per_page' => max(1, min(8, (int) $s['product_count']))]);
        $products = $res['items'];
        $link = category_url($cat);
        $name = $cat['name'];
        $s['image'] = $s['image'] ?: ($cat['banner_image'] ?: $cat['image']);
    }
}
if (!$name) {
    return;
}
$title = $s['title'] ?: $name;
$btnUrl = $s['button_url'] ? safe_link($s['button_url'], $link) : $link;
$layout = $s['layout'] ?? 'split_left';
?>
<section <?= section_attrs($s, 'section--feature feature--' . $layout) ?>>
<?php if ($layout === 'banner'): ?>
  <div class="feature-banner" data-parallax-box>
    <img src="<?= e(media_url($s['image'])) ?>" alt="<?= e($s['image_alt'] ?: $title) ?>" loading="lazy" data-parallax="0.18" width="2400" height="1200">
    <div class="feature-banner__overlay"></div>
    <div class="container feature-banner__content"<?= reveal_attr($s) ?>>
      <?php if ($s['eyebrow']): ?><p class="eyebrow eyebrow--gold"><?= e($s['eyebrow']) ?></p><?php endif; ?>
      <h2 class="section-title"><?= e($title) ?></h2>
      <?php if ($s['description']): ?><p class="section-sub"><?= e(excerpt($s['description'], 260)) ?></p><?php endif; ?>
      <a class="btn-lux btn-lux--light" href="<?= e($btnUrl) ?>"><?= e($s['button_label'] ?: 'Explore') ?></a>
    </div>
  </div>
<?php else: ?>
  <div class="<?= e(section_container_class($s)) ?>">
    <div class="feature">
      <a class="feature__media" href="<?= e($btnUrl) ?>"<?= reveal_attr($s) ?>>
        <img src="<?= e(media_url($s['image'])) ?>" alt="<?= e($s['image_alt'] ?: $title) ?>" loading="lazy" width="1200" height="1500">
        <span class="feature__label"><?= e($name) ?></span>
      </a>
      <div class="feature__content">
        <div class="feature__intro"<?= reveal_attr($s, 100) ?>>
          <?php if ($s['eyebrow']): ?><p class="eyebrow"><?= e($s['eyebrow']) ?></p><?php endif; ?>
          <h2 class="section-title"><?= e($title) ?></h2>
          <?php if ($s['description']): ?><p class="section-sub"><?= e(excerpt($s['description'], 260)) ?></p><?php endif; ?>
        </div>
        <?php if ($products): ?>
          <div class="feature__grid">
            <?php foreach (array_slice($products, 0, 4) as $i => $p): partial('product-card', ['p' => $p, 'reveal' => reveal_attr($s, 150 + $i * 80)]); endforeach; ?>
          </div>
        <?php endif; ?>
        <a class="btn-lux" href="<?= e($btnUrl) ?>"<?= reveal_attr($s, 200) ?>><?= e($s['button_label'] ?: 'Explore') ?></a>
      </div>
    </div>
  </div>
<?php endif; ?>
</section>
