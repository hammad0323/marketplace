<?php
/**
 * /shop/ - all products   |   /search/?q= - search results
 */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
require FS_ROOT . '/partials/listing.php';

$f = listing_filters();
$isSearch = $f['q'] !== '';
$base = [];
if ($isSearch) {
    $ids = search_product_ids($f['q'], ['limit' => 1000]);
    $base = ['ids' => $ids, 'sort' => 'ids'];
    if (input_int('page', 1) === 1) search_log($f['q'], count($ids));
}
$res = listing_run($base, $f);

// Category facet: root categories (or matching categories for search)
$subcats = [];
$catSource = $isSearch ? search_categories($f['q'], 8) : category_children(null);
foreach ($catSource as $c) {
    $subcats[] = ['id' => (int) $c['id'], 'name' => $c['name'], 'count' => category_product_count((int) $c['id']),
        'href' => $isSearch ? url_with(['cat' => (int) $c['id'], 'page' => null]) : category_url($c)];
}

if ($isSearch) {
    page_set(['title' => 'Search results for "' . $f['q'] . '"', 'description' => 'Search results for ' . $f['q'] . ' at Fahim Store.', 'robots' => 'noindex,follow']);
    $crumbs = [['Home', url('')], ['Search', null]];
} else {
    seo_apply(seo_get('static', 0, 'shop'), ['title' => 'Shop All Products', 'description' => 'Browse all school books, stationery, groceries and general items at Fahim Store.',
        'canonical' => abs_url('shop/') . ($res['pager']['page'] > 1 ? '?page=' . $res['pager']['page'] : '')]);
    if ($f['brands'] || $f['min'] !== null || $f['max'] !== null || $f['in_stock'] || $f['on_sale'] || $f['sort']) page_set(['robots' => 'noindex,follow']);
    $crumbs = [['Home', url('')], ['Shop', null]];
}
page_set(['breadcrumbs' => $crumbs]);
require FS_ROOT . '/partials/header.php';
?>
<div class="container">
  <div class="page-head">
    <?= breadcrumbs_html($crumbs) ?>
    <?php if ($isSearch): ?>
      <h1>Results for “<?= e($f['q']) ?>”</h1>
      <p class="text-muted mb-0"><?= (int) $res['total'] ?> products found<?php if ($f['cat'] && ($cc = category_get($f['cat']))): ?> in <strong><?= e($cc['name']) ?></strong> <a href="<?= e(url_with(['cat' => null])) ?>" class="small">(clear)</a><?php endif; ?></p>
    <?php else: ?>
      <h1>Shop</h1>
    <?php endif; ?>
  </div>
  <?php if ($isSearch && !$res['total']): ?>
    <div class="empty-state my-3">
      <i class="bi bi-search"></i>
      <p class="mb-2">We couldn't find anything for “<?= e($f['q']) ?>”.</p>
      <p class="small">Try a simpler word like <em>atta</em>, <em>register</em> or <em>class 5</em>, or browse popular searches:</p>
      <div class="d-flex flex-wrap gap-2 justify-content-center">
        <?php foreach (search_popular(8) as $ps): ?><a class="ss-chip" href="<?= url('search/') ?>?q=<?= urlencode($ps) ?>"><?= e($ps) ?></a><?php endforeach; ?>
      </div>
    </div>
  <?php else: ?>
    <?= listing_layout(listing_sidebar($f, $res['brands'], $subcats, $f['cat'] ?: null), listing_results($res, $f, $isSearch)) ?>
  <?php endif; ?>
</div>
<?php require FS_ROOT . '/partials/footer.php';
