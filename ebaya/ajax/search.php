<?php
/** Predictive search for the header overlay. */
if (!defined('EBAYA')) { http_response_code(403); exit; }
rate_limit_or_fail('search', 90, 60);
$q = mb_substr(trim((string)get('q')), 0, 100);
if (mb_strlen($q) < 2) json_out(['ok' => true, 'html' => '']);
[$items, $total] = products_query(['q' => $q, 'limit' => 6, 'sort' => 'featured']);
$cats = db_all("SELECT name, slug FROM categories WHERE status = 'active' AND name LIKE ? ORDER BY sort_order LIMIT 4", ['%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%']);
ob_start();
if (!$items && !$cats): ?><p class="text-muted">No matches for “<?= e($q) ?>”.</p><?php else: ?>
  <?php if ($cats): ?><div class="sr-cats"><?php foreach ($cats as $c): ?><a class="chip" href="<?= e(url('category/' . $c['slug'])) ?>"><?= e($c['name']) ?></a><?php endforeach; ?></div><?php endif; ?>
  <div class="sr-grid"><?php foreach ($items as $p): ?>
    <a class="sr-item" href="<?= e(product_url($p)) ?>"><img src="<?= e(img_url($p['image'])) ?>" alt="" loading="lazy"><span><?= e($p['name']) ?><small><?= money($p['price']) ?></small></span></a>
  <?php endforeach; ?></div>
  <?php if ($total > count($items)): ?><a class="sr-all" href="<?= e(url('search?q=' . urlencode($q))) ?>">See all <?= (int)$total ?> results <i class="bi bi-arrow-right"></i></a><?php endif; ?>
<?php endif;
json_out(['ok' => true, 'html' => ob_get_clean()]);
