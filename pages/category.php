<?php
/**
 * /category/{root}/{sub}/{child}/ - subcategory cards + products (incl. descendants)
 */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
require FS_ROOT . '/partials/listing.php';

$slugs = $GLOBALS['ROUTE_PARAMS']['slugs'];
$cat = category_by_slug(end($slugs));
if (!$cat || !category_is_visible((int) $cat['id'])) route_404();
// Canonical path check: wrong/old parent path -> 301 to the real one
$canonicalPath = category_url($cat);
$requested = url('category/' . implode('/', $slugs) . '/');
if ($requested !== $canonicalPath) {
    redirect($canonicalPath . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''), 301);
}

$f = listing_filters();
$f['cat'] = 0; // category fixed by URL
$res = listing_run(['category_id' => (int) $cat['id']], $f);
$children = category_children((int) $cat['id']);
$path = category_path((int) $cat['id']);

$crumbs = [['Home', url('')]];
foreach ($path as $pc) $crumbs[] = [$pc['name'], category_url($pc)];
$seo = seo_get('category', (int) $cat['id']);
if ($seo['breadcrumb_title']) $crumbs[count($crumbs) - 1][0] = $seo['breadcrumb_title'];
$parentNames = implode(' ', array_column(array_slice($path, 0, -1), 'name'));
seo_apply($seo, [
    'title' => $cat['name'] . ($parentNames ? ' - ' . $parentNames : '') . ' | Buy Online',
    'description' => $cat['description'] ? str_limit($cat['description'], 160) : 'Shop ' . $cat['name'] . ' online at Fahim Store. ' . $res['total'] . ' products with home delivery and store pickup.',
    'canonical' => abs_url(ltrim(substr($canonicalPath, strlen(base_path())), '/')) . ($res['pager']['page'] > 1 ? '?page=' . $res['pager']['page'] : ''),
    'og_image' => $cat['image'] ? upload_url($cat['image']) : '',
]);
if ($f['brands'] || $f['min'] !== null || $f['max'] !== null || $f['in_stock'] || $f['on_sale'] || $f['sort']) page_set(['robots' => 'noindex,follow']);
page_set(['breadcrumbs' => $crumbs]);
page_add_schema(['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => $cat['name'], 'url' => abs_url(ltrim(substr($canonicalPath, strlen(base_path())), '/')),
    'description' => page_get('description')]);

// Sidebar: sibling/child navigation
$navCats = $children ?: category_children($cat['parent_id'] !== null ? (int) $cat['parent_id'] : null);
$subcats = array_map(function ($c) {
    return ['id' => (int) $c['id'], 'name' => $c['name'], 'count' => category_product_count((int) $c['id']), 'href' => category_url($c)];
}, $navCats);

require FS_ROOT . '/partials/header.php';
?>
<div class="container">
  <div class="page-head">
    <?= breadcrumbs_html($crumbs) ?>
    <h1><?= e($cat['name']) ?></h1>
  </div>

  <?php if ($children && $res['pager']['page'] === 1): ?>
  <section class="mb-4" aria-label="<?= e($cat['name']) ?> categories">
    <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-4 g-2 g-md-4 subcat-grid">
      <?php foreach ($children as $ch): ?>
        <div class="col"><?= render_category_card($ch, true) ?></div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($res['total'] || $f['brands'] || $f['min'] !== null || $f['in_stock']): ?>
  <h2 class="h5 fw-bold mb-3"><?= $children ? 'All ' . e($cat['name']) . ' products' : e($res['total'] . ' products') ?></h2>
  <?= listing_layout(listing_sidebar($f, $res['brands'], $subcats, (int) $cat['id']), listing_results($res, $f)) ?>
  <?php elseif (!$children): ?>
    <div class="empty-state"><i class="bi bi-box-seam"></i><p>No products in this category yet. Please check back soon.</p><a href="<?= url('shop/') ?>" class="btn btn-brand">Browse all products</a></div>
  <?php endif; ?>

  <?php if ($cat['description'] && $res['pager']['page'] === 1): ?>
  <section class="cat-desc mt-4 small"><h2 class="h6 fw-bold">About <?= e($cat['name']) ?></h2><p><?= nl2br(e($cat['description'])) ?></p></section>
  <?php endif; ?>
</div>
<?php require FS_ROOT . '/partials/footer.php';
