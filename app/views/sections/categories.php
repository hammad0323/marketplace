<?php
$limit = max(1, min(12, (int) $s['limit']));
if (($s['source'] ?? 'flagged') === 'selected' && !empty($s['category_ids'])) {
    $ids = array_map('intval', (array) $s['category_ids']);
    $cats = db_all('SELECT * FROM categories WHERE is_active = 1 AND id IN (' . db_in($ids) . ') ORDER BY FIELD(id, ' . db_in($ids) . ')', array_merge($ids, $ids));
} else {
    $cats = db_all('SELECT * FROM categories WHERE is_active = 1 AND show_on_home = 1 ORDER BY sort_order, name');
}
$cats = array_slice($cats, 0, $limit);
if (!$cats) {
    return;
}
$counts = [];
foreach (db_all("SELECT COALESCE(subcategory_id, category_id) cid, category_id pid, COUNT(*) n FROM products WHERE status = 'published' GROUP BY category_id, subcategory_id") as $r) {
    $counts[(int) $r['pid']] = ($counts[(int) $r['pid']] ?? 0) + (int) $r['n'];
    if ((int) $r['cid'] !== (int) $r['pid']) {
        $counts[(int) $r['cid']] = ($counts[(int) $r['cid']] ?? 0) + (int) $r['n'];
    }
}
?>
<section <?= section_attrs($s, 'section--categories') ?>>
  <div class="<?= e(section_container_class($s)) ?>">
    <?php view('sections/_heading', ['s' => $s, 'center' => true]); ?>
    <div class="cat-grid cat-grid--<?= e($s['layout']) ?> cat-grid--n<?= count($cats) ?>">
      <?php foreach ($cats as $i => $c): ?>
        <a class="cat-tile" href="<?= e(category_url($c)) ?>"<?= reveal_attr($s, ($i % 3) * 120) ?>>
          <?php $wide = $s['layout'] === 'mosaic' && count($cats) === 6 && $i === 5 && $c['banner_image']; ?>
          <div class="cat-tile__img"><img src="<?= e(media_url($wide ? $c['banner_image'] : $c['image'])) ?>" alt="<?= e($c['image_alt'] ?: $c['name']) ?>" loading="lazy" width="900" height="1100"></div>
          <div class="cat-tile__body">
            <h3><?= e($c['name']) ?></h3>
            <?php if ($c['short_description']): ?><p><?= e($c['short_description']) ?></p><?php endif; ?>
            <span class="cat-tile__cta">Discover<?= !empty($counts[(int) $c['id']]) ? ' · ' . (int) $counts[(int) $c['id']] . ' pieces' : '' ?> <i class="bi bi-arrow-right"></i></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
