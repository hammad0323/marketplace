<?php
/** Product listing: shop, category, collection, new arrivals, best sellers, search. */
if (!defined('EBAYA')) { http_response_code(403); exit; }

$f = listing_filters_from_request();
$perPage = 24;
$page = max(1, (int)get('page', 1));
$opts = ['values' => $f['values'], 'price_min' => $f['price_min'], 'price_max' => $f['price_max'], 'in_stock' => $f['in_stock'], 'fulfillment' => $f['fulfillment'], 'sort' => $f['sort']];
$title = 'Shop All Abayas';
$intro = '';
$banner = null;
$crumbs = [];
$subcats = [];
$seo = ['title' => 'Shop All Abayas', 'description' => null, 'noindex' => false];
$current = null;

switch ($mode) {
    case 'category':
        $cat = db_one("SELECT * FROM categories WHERE slug = ? AND status = 'active'", [$slug]);
        if (!$cat) {
            if ($r = redirect_lookup('/category/' . $slug)) redirect(url($r['to_path']), 301);
            not_found();
        }
        $current = $cat;
        $opts['category_ids'] = category_descendant_ids((int)$cat['id']);
        $title = $cat['name'];
        $intro = $cat['description'];
        $banner = $cat['banner_image'];
        foreach (category_ancestors((int)$cat['id']) as $a) $crumbs[] = [$a['name'], (int)$a['id'] === (int)$cat['id'] ? null : category_url($a)];
        $subcats = array_values(array_filter(categories_all(), fn($c) => (int)$c['parent_id'] === (int)$cat['id']));
        $seo = ['title' => $cat['seo_title'] ?: $cat['name'], 'description' => $cat['meta_description'] ?: $cat['description'], 'image' => $cat['og_image'] ?: $cat['banner_image'] ?: $cat['image'],
                'canonical' => abs_url('category/' . $cat['slug']), 'noindex' => (bool)$cat['noindex']];
        break;
    case 'collection':
        $col = db_one("SELECT * FROM collections WHERE slug = ? AND status = 'active'", [$slug]);
        if (!$col) {
            if ($r = redirect_lookup('/collections/' . $slug)) redirect(url($r['to_path']), 301);
            not_found();
        }
        $opts['collection_id'] = (int)$col['id'];
        if ($f['sort'] === 'featured' && get('sort') === '') $opts['sort'] = 'manual';
        $title = $col['name'];
        $intro = $col['description'];
        $banner = $col['banner_image'];
        $crumbs = [['Collections', url('collections')], [$col['name'], null]];
        $seo = ['title' => $col['seo_title'] ?: $col['name'], 'description' => $col['meta_description'] ?: $col['description'], 'image' => $col['banner_image'] ?: $col['image'],
                'canonical' => abs_url('collections/' . $col['slug']), 'noindex' => (bool)$col['noindex']];
        break;
    case 'new':
        $opts['flag'] = 'new_arrival';
        if (get('sort') === '') $opts['sort'] = 'newest';
        $title = 'New Arrivals';
        $intro = 'The latest designs from our atelier.';
        $crumbs = [['New Arrivals', null]];
        $seo = ['title' => 'New Arrivals', 'canonical' => abs_url('new-arrivals')];
        break;
    case 'best':
        if (get('sort') === '') $opts['sort'] = 'best_selling';
        $ids = best_seller_ids(48);
        $flagged = db_col("SELECT id FROM products WHERE is_best_seller = 1 AND status = 'published'");
        $opts['ids'] = array_values(array_unique(array_merge($ids, array_map('intval', $flagged))));
        $title = 'Best Sellers';
        $intro = 'Our most loved pieces.';
        $crumbs = [['Best Sellers', null]];
        $seo = ['title' => 'Best Sellers', 'canonical' => abs_url('best-sellers')];
        break;
    case 'search':
        $opts['q'] = $f['q'];
        $title = $f['q'] !== '' ? 'Results for “' . $f['q'] . '”' : 'Search';
        $crumbs = [['Search', null]];
        $seo = ['title' => 'Search', 'noindex' => true];
        break;
    default:
        $crumbs = [['Shop', null]];
        $seo = ['title' => 'Shop All Abayas', 'canonical' => abs_url('shop')];
}

// Filtered/sorted/paginated variants should not compete with the main listing in search results.
if ($f['values'] || $f['price_min'] !== '' || $f['price_max'] !== '' || $f['in_stock'] || get('sort') !== '' ) $seo['noindex'] = true;

[$probe, $total] = products_query($opts + ['limit' => 1]);
$pg = paginate($total, $perPage, $page);
[$products] = products_query($opts + ['limit' => $perPage, 'offset' => $pg['offset']]);
if ($page > 1) $seo['title'] .= ' — Page ' . $pg['page'];

seo_set($seo);
seo_breadcrumbs($crumbs);
seo_schema(['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => $title, 'url' => $seo['canonical'] ?? abs_url(ltrim($GLOBALS['route_path'], '/'))]);

$facets = filter_facets();
$sorts = ['featured' => 'Featured', 'newest' => 'Newest', 'best_selling' => 'Best selling', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'rating' => 'Top rated', 'name' => 'Name A–Z'];
$activeCount = array_sum(array_map('count', $f['values'])) + ($f['price_min'] !== '' ? 1 : 0) + ($f['price_max'] !== '' ? 1 : 0) + ($f['in_stock'] ? 1 : 0) + ($f['fulfillment'] ? 1 : 0);

$bodyClass = 'page-listing';
require ROOT_PATH . '/templates/header.php';
?>
<section class="listing-hero<?= $banner ? ' has-banner' : '' ?>"<?= $banner ? ' style="--banner:url(' . e(img_url($banner)) . ')"' : '' ?>>
  <?php if ($banner): ?><div class="listing-hero-bg parallax-bg" aria-hidden="true"></div><?php endif; ?>
  <div class="container-eb">
    <?php include ROOT_PATH . '/templates/breadcrumbs.php'; ?>
    <h1 class="page-title" data-reveal><?= e($title) ?></h1>
    <?php if ($intro): ?><p class="page-intro" data-reveal><?= e($intro) ?></p><?php endif; ?>
    <?php if ($subcats): ?>
      <div class="subcat-chips" data-reveal>
        <?php foreach ($subcats as $sc): ?><a href="<?= e(category_url($sc)) ?>" class="chip"><?= e($sc['name']) ?></a><?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="listing-body">
  <div class="container-eb">
    <div class="listing-toolbar">
      <button class="btn btn-eb btn-outline-eb btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#filters"><i class="bi bi-sliders"></i> Filter<?= $activeCount ? ' (' . $activeCount . ')' : '' ?></button>
      <span class="result-count"><?= number_format($total) ?> <?= $total === 1 ? 'piece' : 'pieces' ?></span>
      <form method="get" class="sort-form">
        <?php foreach ($_GET as $k => $v): if (in_array($k, ['sort', 'page', 'route'], true)) continue; foreach ((array)$v as $vv): ?>
          <input type="hidden" name="<?= e($k) . (is_array($v) ? '[]' : '') ?>" value="<?= e($vv) ?>">
        <?php endforeach; endforeach; ?>
        <label for="sortSel" class="visually-hidden">Sort by</label>
        <select id="sortSel" name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
          <?php foreach ($sorts as $k => $l): ?><option value="<?= e($k) ?>"<?= ($opts['sort'] === $k) ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
      </form>
    </div>

    <div class="offcanvas offcanvas-start filters-panel" tabindex="-1" id="filters" aria-labelledby="filtersTitle">
      <div class="offcanvas-header"><h2 class="h5 offcanvas-title" id="filtersTitle">Filter</h2><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button></div>
      <div class="offcanvas-body">
        <form method="get" id="filterForm">
          <?php if ($mode === 'search'): ?><input type="hidden" name="q" value="<?= e($f['q']) ?>"><?php endif; ?>
          <input type="hidden" name="sort" value="<?= e(get('sort')) ?>">
          <div class="filter-group">
            <h3>Price (<?= e(currency_symbol()) ?>)</h3>
            <div class="d-flex gap-2">
              <input type="number" min="0" name="price_min" class="form-control form-control-sm" placeholder="Min" value="<?= e($f['price_min']) ?>">
              <input type="number" min="0" name="price_max" class="form-control form-control-sm" placeholder="Max" value="<?= e($f['price_max']) ?>">
            </div>
          </div>
          <?php foreach ($facets as $code => $fac): ?>
            <div class="filter-group">
              <h3><?= e($fac['name']) ?></h3>
              <div class="<?= $code === 'color' ? 'filter-swatches' : 'filter-checks' ?>">
                <?php foreach ($fac['values'] as $v): $checked = in_array((int)$v['id'], $f['values'][$code] ?? [], true); ?>
                  <label class="<?= $code === 'color' ? 'swatch-check' : ($code === 'size' ? 'size-check' : 'form-check') ?>">
                    <input type="checkbox" name="<?= e($code) ?>[]" value="<?= (int)$v['id'] ?>"<?= $checked ? ' checked' : '' ?> class="<?= $code === 'color' || $code === 'size' ? 'visually-hidden' : 'form-check-input' ?>">
                    <?php if ($code === 'color'): ?><span class="swatch lg" style="background:<?= e($v['swatch_hex'] ?: '#ccc') ?>"></span><span class="sw-label"><?= e($v['value']) ?></span>
                    <?php elseif ($code === 'size'): ?><span><?= e($v['value']) ?></span>
                    <?php else: ?><span class="form-check-label"><?= e($v['value']) ?></span><?php endif; ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
          <div class="filter-group">
            <h3>Availability</h3>
            <label class="form-check"><input class="form-check-input" type="checkbox" name="in_stock" value="1"<?= $f['in_stock'] ? ' checked' : '' ?>> <span class="form-check-label">In stock only</span></label>
            <label class="form-check"><input class="form-check-input" type="radio" name="fulfillment" value="ready_to_ship"<?= $f['fulfillment'] === 'ready_to_ship' ? ' checked' : '' ?>> <span class="form-check-label">Ready to ship</span></label>
            <label class="form-check"><input class="form-check-input" type="radio" name="fulfillment" value="made_to_order"<?= $f['fulfillment'] === 'made_to_order' ? ' checked' : '' ?>> <span class="form-check-label">Made to order</span></label>
          </div>
          <div class="filter-actions">
            <button class="btn btn-eb btn-primary-eb w-100" type="submit">Show results</button>
            <a class="btn btn-link w-100" href="<?= e(strtok($_SERVER['REQUEST_URI'], '?') . ($mode === 'search' ? '?q=' . urlencode($f['q']) : '')) ?>">Clear all</a>
          </div>
        </form>
      </div>
    </div>

    <?php if ($products): ?>
      <div class="product-grid">
        <?php foreach ($products as $i => $p): ?><div data-reveal style="--d:<?= ($i % 4) * 70 ?>ms"><?php include ROOT_PATH . '/templates/product-card.php'; ?></div><?php endforeach; ?>
      </div>
      <?php if ($pg['pages'] > 1): ?>
        <nav class="pager" aria-label="Pages">
          <?php if ($pg['page'] > 1): ?><a href="<?= e(query_with(['page' => $pg['page'] - 1])) ?>" rel="prev" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a><?php endif; ?>
          <?php for ($i = max(1, $pg['page'] - 2); $i <= min($pg['pages'], $pg['page'] + 2); $i++): ?>
            <a href="<?= e(query_with(['page' => $i > 1 ? $i : null])) ?>"<?= $i === $pg['page'] ? ' class="is-current" aria-current="page"' : '' ?>><?= $i ?></a>
          <?php endfor; ?>
          <?php if ($pg['page'] < $pg['pages']): ?><a href="<?= e(query_with(['page' => $pg['page'] + 1])) ?>" rel="next" aria-label="Next page"><i class="bi bi-chevron-right"></i></a><?php endif; ?>
        </nav>
      <?php endif; ?>
    <?php else: ?>
      <div class="empty-state">
        <i class="bi bi-flower2"></i>
        <h2>No pieces found</h2>
        <p><?= $mode === 'search' && $f['q'] === '' ? 'Type a word above to search our collection.' : 'Try removing a filter or browsing the full collection.' ?></p>
        <a class="btn btn-eb btn-primary-eb" href="<?= e(url('shop')) ?>">Shop all abayas</a>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require ROOT_PATH . '/templates/footer.php';
