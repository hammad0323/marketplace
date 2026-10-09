<?php
/**
 * Product listing: /shop, /search, /category/{slug}, /collection/{slug}
 */
$mode = $GLOBALS['route']['mode'];
$category = null;
$collection = null;
$filters = [
    'q' => mb_substr(input('q', '', 'get'), 0, 100),
    'min_price' => input('min_price', '', 'get'),
    'max_price' => input('max_price', '', 'get'),
    'colors' => array_map('strval', input_array('color', 'get')),
    'materials' => array_map('strval', input_array('material', 'get')),
    'types' => array_map('strval', input_array('type', 'get')),
    'availability' => in_array(input('availability', '', 'get'), ['in_stock', 'out_of_stock'], true) ? input('availability', '', 'get') : '',
    'sort' => in_array(input('sort', '', 'get'), ['featured', 'newest', 'price_asc', 'price_desc', 'popular', 'relevance', 'name'], true) ? input('sort', '', 'get') : null,
    'page' => max(1, input_int('page', 1, 'get')),
    'per_page' => (int) setting('products_per_page', '12'),
];
$scopeIds = [];
$crumbs = [['name' => 'Home', 'url' => path_url('/')]];
$heading = 'Shop All';
$intro = '';
$banner = null;
$subcats = [];

if ($mode === 'category') {
    $category = category_by_slug($GLOBALS['route']['slug']);
    if (!$category) {
        not_found();
    }
    $scopeIds = category_descendant_ids((int) $category['id']);
    $filters['category_ids'] = $scopeIds;
    foreach (category_ancestors($category) as $anc) {
        $crumbs[] = ['name' => $anc['name'], 'url' => category_url($anc)];
    }
    $heading = $category['name'];
    $intro = $category['description'];
    $banner = $category['banner_image'] ?: null;
    $subcats = db_all('SELECT * FROM categories WHERE parent_id = ? AND is_active = 1 ORDER BY sort_order, name', [(int) $category['id']]);
    if (!$subcats && $category['parent_id']) {
        $subcats = db_all('SELECT * FROM categories WHERE parent_id = ? AND is_active = 1 ORDER BY sort_order, name', [(int) $category['parent_id']]);
    }
    meta_set([
        'title' => $category['seo_title'] ?: $category['name'],
        'description' => $category['meta_description'] ?: excerpt($category['description'] ?: $category['short_description'], 160),
        'image' => $category['banner_image'] ?: $category['image'],
        'canonical' => 'category/' . $category['slug'] . ($filters['page'] > 1 ? '?page=' . $filters['page'] : ''),
    ]);
} elseif ($mode === 'collection') {
    $collection = collection_by_slug($GLOBALS['route']['slug']);
    if (!$collection) {
        not_found();
    }
    $filters['collection_id'] = (int) $collection['id'];
    $crumbs[] = ['name' => 'Collections', 'url' => path_url('shop')];
    $crumbs[] = ['name' => $collection['name'], 'url' => path_url('collection/' . $collection['slug'])];
    $heading = $collection['name'];
    $intro = $collection['description'];
    $banner = $collection['image'];
    meta_set([
        'title' => $collection['seo_title'] ?: $collection['name'],
        'description' => $collection['meta_description'] ?: excerpt($collection['description'], 160),
        'image' => $collection['image'],
        'canonical' => 'collection/' . $collection['slug'] . ($filters['page'] > 1 ? '?page=' . $filters['page'] : ''),
    ]);
} elseif ($mode === 'search') {
    $heading = $filters['q'] !== '' ? 'Results for “' . $filters['q'] . '”' : 'Search';
    $crumbs[] = ['name' => 'Search', 'url' => path_url('search')];
    meta_set(['title' => $filters['q'] !== '' ? 'Search: ' . $filters['q'] : 'Search', 'noindex' => true]);
} else {
    $crumbs[] = ['name' => 'Shop', 'url' => path_url('shop')];
    $heading = setting('shop_heading', 'Shop All');
    $intro = setting('shop_intro', '');
    meta_set([
        'title' => setting('shop_seo_title', 'Shop Leather Wallets & Accessories'),
        'description' => setting('shop_meta_description', setting('seo_default_description', '')),
        'canonical' => 'shop' . ($filters['page'] > 1 ? '?page=' . $filters['page'] : ''),
    ]);
}

// Filtered/sorted variants of a listing are not indexed (canonical points to the clean URL).
$hasFilters = $filters['min_price'] !== '' || $filters['max_price'] !== '' || $filters['colors'] || $filters['materials'] || $filters['types'] || $filters['availability'] || $filters['sort'] || ($filters['q'] !== '' && $mode !== 'search');
if ($hasFilters) {
    meta_set(['noindex' => true]);
}

$result = products_query($filters);
$pg = $result['pagination'];
if ($filters['page'] > $pg['pages'] && $pg['total'] > 0) {
    not_found();
}
pagination_meta($pg);
$facets = product_facets($scopeIds, $filters['collection_id'] ?? null);
meta_set(['breadcrumbs' => $crumbs]);

$sortOptions = ['featured' => 'Featured', 'newest' => 'Newest', 'popular' => 'Most popular', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low'];
if ($filters['q'] !== '') {
    $sortOptions = ['relevance' => 'Relevance'] + $sortOptions;
}
$activeCount = count($filters['colors']) + count($filters['materials']) + count($filters['types']) + ($filters['availability'] ? 1 : 0) + ($filters['min_price'] !== '' || $filters['max_price'] !== '' ? 1 : 0);

partial('header');
?>
<section class="page-hero<?= $banner ? ' page-hero--image' : '' ?>">
  <?php if ($banner): ?><div class="page-hero__bg" data-parallax-box><img src="<?= e(media_url($banner)) ?>" alt="" data-parallax="0.2"></div><?php endif; ?>
  <div class="container container--wide page-hero__inner">
    <?php partial('breadcrumbs'); ?>
    <h1 class="page-title" data-reveal="fade-up"><?= e($heading) ?></h1>
    <?php if ($intro): ?><div class="page-intro" data-reveal="fade-up"><?= rich_text(excerpt($intro, 400)) ?></div><?php endif; ?>
  </div>
</section>

<div class="container container--wide listing">
  <?php if ($subcats): ?>
    <nav class="subcat-chips" aria-label="Subcategories">
      <?php if ($category && $category['parent_id']): $parent = category_by_id((int) $category['parent_id']); ?>
        <a href="<?= e(category_url($parent)) ?>" class="chip">All <?= e($parent['name']) ?></a>
      <?php elseif ($category): ?>
        <a href="<?= e(category_url($category)) ?>" class="chip is-active">All</a>
      <?php endif; ?>
      <?php foreach ($subcats as $sc): ?><a href="<?= e(category_url($sc)) ?>" class="chip<?= $category && (int) $sc['id'] === (int) $category['id'] ? ' is-active' : '' ?>"><?= e($sc['name']) ?></a><?php endforeach; ?>
    </nav>
  <?php endif; ?>

  <div class="listing__toolbar">
    <button class="btn-filter d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#filters"><i class="bi bi-sliders"></i> Filters<?= $activeCount ? ' (' . $activeCount . ')' : '' ?></button>
    <p class="listing__count"><?= (int) $pg['total'] ?> <?= $pg['total'] === 1 ? 'piece' : 'pieces' ?></p>
    <form method="get" class="listing__sort">
      <?php foreach ($_GET as $k => $v): if (in_array($k, ['sort', 'page', '_route'], true)) continue; foreach ((array) $v as $vv): ?>
        <input type="hidden" name="<?= e($k) ?><?= is_array($v) ? '[]' : '' ?>" value="<?= e($vv) ?>">
      <?php endforeach; endforeach; ?>
      <label for="sort" class="visually-hidden">Sort by</label>
      <select id="sort" name="sort" onchange="this.form.submit()">
        <?php foreach ($sortOptions as $k => $label): ?><option value="<?= e($k) ?>"<?= $result['sort'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
    </form>
  </div>

  <div class="listing__layout">
    <aside class="offcanvas-lg offcanvas-start listing__filters" tabindex="-1" id="filters" aria-labelledby="filtersLabel">
      <div class="offcanvas-header">
        <h2 class="h5 offcanvas-title" id="filtersLabel">Filters</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#filters" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body">
        <form method="get" class="filters" data-filter-form>
          <?php if ($mode === 'search'): ?><input type="hidden" name="q" value="<?= e($filters['q']) ?>"><?php endif; ?>
          <?php if ($filters['sort']): ?><input type="hidden" name="sort" value="<?= e($filters['sort']) ?>"><?php endif; ?>
          <?php if ($mode !== 'search'): ?>
          <div class="filter-group">
            <h3>Search within</h3>
            <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Keyword" class="form-control">
          </div>
          <?php endif; ?>
          <div class="filter-group">
            <h3>Price</h3>
            <div class="price-range">
              <input type="number" name="min_price" class="form-control" min="0" step="1" placeholder="<?= e((string) floor((float) ($facets['price']['min_p'] ?? 0))) ?>" value="<?= e($filters['min_price']) ?>" aria-label="Minimum price">
              <span>–</span>
              <input type="number" name="max_price" class="form-control" min="0" step="1" placeholder="<?= e((string) ceil((float) ($facets['price']['max_p'] ?? 0))) ?>" value="<?= e($filters['max_price']) ?>" aria-label="Maximum price">
            </div>
          </div>
          <div class="filter-group">
            <h3>Availability</h3>
            <label class="check"><input type="radio" name="availability" value="" <?= $filters['availability'] === '' ? 'checked' : '' ?>> All</label>
            <label class="check"><input type="radio" name="availability" value="in_stock" <?= $filters['availability'] === 'in_stock' ? 'checked' : '' ?>> In stock</label>
            <label class="check"><input type="radio" name="availability" value="out_of_stock" <?= $filters['availability'] === 'out_of_stock' ? 'checked' : '' ?>> Sold out</label>
          </div>
          <?php if ($facets['colors']): ?>
          <div class="filter-group">
            <h3>Colour</h3>
            <div class="color-filter">
              <?php foreach ($facets['colors'] as $c): $on = in_array($c['color_name'], $filters['colors'], true); ?>
                <label class="color-opt<?= $on ? ' is-on' : '' ?>" title="<?= e($c['color_name']) ?>">
                  <input type="checkbox" name="color[]" value="<?= e($c['color_name']) ?>" <?= $on ? 'checked' : '' ?>>
                  <span class="color-opt__dot" style="--sw: <?= e(valid_hex((string) $c['color_hex']) ? $c['color_hex'] : '#999999') ?>"></span>
                  <span class="color-opt__name"><?= e($c['color_name']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
          <?php foreach (['types' => ['Wallet type', 'type'], 'materials' => ['Material', 'material']] as $fk => [$flabel, $fname]): if (!$facets[$fk]) continue; ?>
          <div class="filter-group">
            <h3><?= e($flabel) ?></h3>
            <?php foreach ($facets[$fk] as $opt): $on = in_array($opt['value'], $filters[$fk], true); ?>
              <label class="check"><input type="checkbox" name="<?= e($fname) ?>[]" value="<?= e($opt['value']) ?>" <?= $on ? 'checked' : '' ?>> <?= e($opt['value']) ?> <span class="text-muted">(<?= (int) $opt['n'] ?>)</span></label>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>
          <div class="filters__actions">
            <button type="submit" class="btn-lux w-100">Apply filters</button>
            <?php if ($activeCount || ($filters['q'] !== '' && $mode !== 'search')): ?><a class="link-muted" href="<?= e(path_url(current_path(), $mode === 'search' ? ['q' => $filters['q']] : [])) ?>">Clear all</a><?php endif; ?>
          </div>
        </form>
      </div>
    </aside>

    <div class="listing__results">
      <?php if ($result['items']): ?>
        <div class="product-grid">
          <?php foreach ($result['items'] as $i => $p): partial('product-card', ['p' => $p, 'eager' => $i < 4, 'reveal' => ' data-reveal="fade-up" style="--reveal-delay:' . (($i % 4) * 70) . 'ms"']); endforeach; ?>
        </div>
        <?php partial('pagination', ['pg' => $pg]); ?>
      <?php else: ?>
        <div class="empty-state">
          <i class="bi bi-search"></i>
          <h2>Nothing matched</h2>
          <p><?= $filters['q'] !== '' ? 'We could not find products for “' . e($filters['q']) . '”. Try a broader term.' : 'No products match these filters yet.' ?></p>
          <a class="btn-lux" href="<?= e(path_url('shop')) ?>">Browse all products</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($category && $category['description'] && mb_strlen(strip_tags($category['description'])) > 400): ?>
    <div class="category-copy prose"><?= rich_text($category['description']) ?></div>
  <?php endif; ?>
</div>
<?php partial('footer');
