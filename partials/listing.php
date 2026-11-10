<?php
/**
 * Shared product listing with filters, sorting and SEO-friendly pagination.
 * Used by: shop/search, category, brand and offer pages.
 */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
require_once FS_ROOT . '/partials/product-card.php';

/**
 * Collect filter state from the query string.
 */
function listing_filters(): array
{
    $sorts = ['relevance', 'newest', 'popular', 'price_asc', 'price_desc', 'name', 'rating', 'discount'];
    $sort = input('sort', '', 'get');
    return [
        'q' => mb_substr(input('q', '', 'get'), 0, 100),
        'brands' => array_values(array_filter(array_map('intval', (array) ($_GET['brand'] ?? [])))),
        'min' => input('min', '', 'get') !== '' ? max(0, (float) input('min', '', 'get')) : null,
        'max' => input('max', '', 'get') !== '' ? max(0, (float) input('max', '', 'get')) : null,
        'in_stock' => input('in_stock', '', 'get') === '1',
        'on_sale' => input('on_sale', '', 'get') === '1',
        'featured' => input('featured', '', 'get') === '1',
        'cat' => input_int('cat'),
        'sort' => in_array($sort, $sorts, true) ? $sort : '',
    ];
}

/**
 * Run the listing query.
 * $base: fixed constraints (category_id, brand_ids, ids)
 * @return array ['items'=>[], 'total'=>int, 'pager'=>[], 'brands'=>[]]
 */
function listing_run(array $base, array $f): array
{
    $per = max(8, (int) setting('products_per_page', 24));
    $o = $base;
    if ($f['brands']) $o['brand_ids'] = $f['brands'];
    if ($f['min'] !== null) $o['min_price'] = $f['min'];
    if ($f['max'] !== null) $o['max_price'] = $f['max'];
    if ($f['in_stock']) $o['in_stock'] = 1;
    if ($f['on_sale']) $o['on_sale'] = 1;
    if ($f['featured']) $o['featured'] = 1;
    if ($f['cat']) $o['category_id'] = $f['cat'];
    $sort = $f['sort'] ?: (isset($o['ids']) && ($o['sort'] ?? '') === 'ids' ? 'relevance' : 'popular');
    $o['sort'] = $sort === 'relevance' ? 'ids' : $sort;
    if ($o['sort'] === 'ids' && !isset($o['ids'])) $o['sort'] = 'popular';
    $o['with_total'] = true;

    // Count first so we can clamp the page
    $count = products_query(array_merge($o, ['limit' => 1]));
    $pager = paginate((int) $count['total'], $per);
    $o['limit'] = $per;
    $o['offset'] = $pager['offset'];
    $res = products_query($o);

    // Brands present in the unfiltered base set (facet)
    $facetIdsSql = '';
    $brands = [];
    $bq = $base;
    unset($bq['brand_ids']);
    $baseIds = array_column(products_query(array_merge($bq, ['limit' => 200, 'sort' => 'popular']))['items'], 'brand_id');
    $baseIds = array_values(array_unique(array_filter(array_map('intval', $baseIds))));
    if ($baseIds) {
        $brands = db_all('SELECT id, name FROM brands WHERE id IN (' . in_placeholders($baseIds) . ') ORDER BY name', $baseIds);
    }
    // rel prev/next for SEO pagination
    if ($pager['page'] > 1) page_set(['prev' => abs_url(ltrim(url_with(['page' => $pager['page'] - 1]), '/'))]);
    if ($pager['page'] < $pager['pages']) page_set(['next' => abs_url(ltrim(url_with(['page' => $pager['page'] + 1]), '/'))]);
    return ['items' => $res['items'], 'total' => (int) $count['total'], 'pager' => $pager, 'brands' => $brands, 'sort' => $sort];
}

/** Sidebar filter form. $subcats = category links to show. */
function listing_sidebar(array $f, array $brands, array $subcats = [], ?int $activeCat = null): string
{
    ob_start(); ?>
<form method="get" class="filters-card js-filter-form" aria-label="Filter products">
  <?php if ($f['q'] !== ''): ?><input type="hidden" name="q" value="<?= e($f['q']) ?>"><?php endif; ?>
  <?php if ($f['sort']): ?><input type="hidden" name="sort" value="<?= e($f['sort']) ?>"><?php endif; ?>
  <?php if ($subcats): ?>
  <div class="filter-block cat-filter">
    <h3>Categories</h3>
    <div class="filter-list">
      <?php foreach ($subcats as $c): ?>
        <a href="<?= e($c['href']) ?>" class="<?= $activeCat === (int) $c['id'] ? 'active' : '' ?>"><span><?= e($c['name']) ?></span><small class="text-muted"><?= (int) $c['count'] ?></small></a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
  <?php if ($f['cat']): ?><input type="hidden" name="cat" value="<?= (int) $f['cat'] ?>"><?php endif; ?>
  <div class="filter-block">
    <h3>Price (<?= e(setting('currency_symbol', 'Rs.')) ?>)</h3>
    <div class="d-flex gap-2">
      <input type="number" name="min" class="form-control form-control-sm" placeholder="Min" min="0" value="<?= $f['min'] !== null ? e($f['min']) : '' ?>" aria-label="Minimum price">
      <input type="number" name="max" class="form-control form-control-sm" placeholder="Max" min="0" value="<?= $f['max'] !== null ? e($f['max']) : '' ?>" aria-label="Maximum price">
    </div>
  </div>
  <div class="filter-block">
    <h3>Availability</h3>
    <div class="form-check"><input class="form-check-input js-auto" type="checkbox" name="in_stock" value="1" id="fStock" <?= $f['in_stock'] ? 'checked' : '' ?>><label class="form-check-label" for="fStock">In stock only</label></div>
    <div class="form-check"><input class="form-check-input js-auto" type="checkbox" name="on_sale" value="1" id="fSale" <?= $f['on_sale'] ? 'checked' : '' ?>><label class="form-check-label" for="fSale">On sale</label></div>
    <div class="form-check"><input class="form-check-input js-auto" type="checkbox" name="featured" value="1" id="fFeat" <?= $f['featured'] ? 'checked' : '' ?>><label class="form-check-label" for="fFeat">Featured</label></div>
  </div>
  <?php if ($brands): ?>
  <div class="filter-block">
    <h3>Brands</h3>
    <div class="filter-list">
      <?php foreach ($brands as $b): ?>
      <div class="form-check"><input class="form-check-input js-auto" type="checkbox" name="brand[]" value="<?= (int) $b['id'] ?>" id="fb<?= (int) $b['id'] ?>" <?= in_array((int) $b['id'], $f['brands'], true) ? 'checked' : '' ?>><label class="form-check-label" for="fb<?= (int) $b['id'] ?>"><?= e($b['name']) ?></label></div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
  <div class="d-flex gap-2 mt-3">
    <button class="btn btn-brand btn-sm flex-grow-1" type="submit">Apply filters</button>
    <a href="<?= e(strtok($_SERVER['REQUEST_URI'], '?') . ($f['q'] !== '' ? '?q=' . urlencode($f['q']) : '')) ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
  </div>
</form>
<script>document.addEventListener('change',function(e){if(e.target.classList.contains('js-auto')){e.target.form.submit();}});</script>
<?php
    return ob_get_clean();
}

/** Results toolbar + grid + pagination. */
function listing_results(array $res, array $f, bool $isSearch = false): string
{
    $sorts = ['popular' => 'Popularity', 'newest' => 'Newest', 'price_asc' => 'Price: Low to High', 'price_desc' => 'Price: High to Low', 'discount' => 'Biggest discount', 'rating' => 'Top rated', 'name' => 'Name A-Z'];
    if ($isSearch) $sorts = ['relevance' => 'Best match'] + $sorts;
    $from = $res['total'] ? $res['pager']['offset'] + 1 : 0;
    $to = $res['pager']['offset'] + count($res['items']);
    ob_start(); ?>
<div class="listing-bar">
  <button class="btn btn-outline-secondary btn-sm d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#filtersCanvas"><i class="bi bi-funnel me-1"></i>Filters</button>
  <span class="small text-muted d-none d-sm-inline">Showing <?= $from ?>–<?= $to ?> of <?= (int) $res['total'] ?> products</span>
  <form method="get" class="d-flex align-items-center gap-2">
    <?php foreach ($_GET as $k => $v): if (in_array($k, ['sort', 'page', 'route'], true)) continue;
      foreach ((array) $v as $vv): ?><input type="hidden" name="<?= e($k) ?><?= is_array($v) ? '[]' : '' ?>" value="<?= e($vv) ?>"><?php endforeach; endforeach; ?>
    <label for="sortSel" class="small text-muted d-none d-md-inline">Sort</label>
    <select name="sort" id="sortSel" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php foreach ($sorts as $k => $label): ?><option value="<?= $k ?>" <?= $res['sort'] === $k ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
    </select>
  </form>
</div>
<?= render_product_grid($res['items'], 'row-cols-2 row-cols-md-3 row-cols-xl-4', $isSearch ? $f['q'] : '') ?>
<div class="mt-4"><?= render_pagination($res['pager']) ?></div>
<?php
    return ob_get_clean();
}

/** Two-column layout with offcanvas filters on mobile. */
function listing_layout(string $sidebar, string $results): string
{
    return '<div class="row g-3"><aside class="col-lg-3 d-none d-lg-block">' . $sidebar . '</aside><div class="col-lg-9">' . $results . '</div></div>'
        . '<div class="offcanvas offcanvas-start" tabindex="-1" id="filtersCanvas" aria-label="Filters"><div class="offcanvas-header"><h2 class="h5 mb-0">Filters</h2>'
        . '<button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button></div><div class="offcanvas-body">'
        . str_replace(['id="f', 'for="f'], ['id="m', 'for="m'], $sidebar) . '</div></div>';
}
