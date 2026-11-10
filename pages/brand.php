<?php
/** /brand/{slug}/ */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
require FS_ROOT . '/partials/listing.php';
$b = db_row('SELECT * FROM brands WHERE slug = ? AND is_active = 1 AND deleted_at IS NULL', [$GLOBALS['ROUTE_PARAMS']['slug']]);
if (!$b) route_404();
$f = listing_filters();
$f['brands'] = [];
$res = listing_run(['brand_ids' => [(int) $b['id']]], $f);
$crumbs = [['Home', url('')], ['Brands', url('brands/')], [$b['name'], null]];
seo_apply(seo_get('brand', (int) $b['id']), ['title' => $b['name'] . ' Products', 'description' => $b['description'] ? str_limit($b['description'], 160) : 'Shop original ' . $b['name'] . ' products online at Fahim Store.',
    'canonical' => abs_url('brand/' . $b['slug'] . '/') . ($res['pager']['page'] > 1 ? '?page=' . $res['pager']['page'] : ''), 'og_image' => $b['logo'] ? upload_url($b['logo']) : '']);
page_set(['breadcrumbs' => $crumbs]);
require FS_ROOT . '/partials/header.php';
?>
<div class="container">
  <div class="page-head d-flex align-items-center gap-3">
    <div><?= breadcrumbs_html($crumbs) ?><h1><?= e($b['name']) ?></h1></div>
    <?php if ($b['logo']): ?><img src="<?= e(upload_url($b['logo'])) ?>" alt="<?= e($b['name']) ?>" height="60" class="ms-auto"><?php endif; ?>
  </div>
  <?php if ($b['description']): ?><p class="cat-desc"><?= nl2br(e($b['description'])) ?></p><?php endif; ?>
  <?= listing_layout(listing_sidebar($f, [], []), listing_results($res, $f)) ?>
</div>
<?php require FS_ROOT . '/partials/footer.php';
