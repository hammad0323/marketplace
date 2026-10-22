<?php
/**
 * Product listing: /shop, /new-arrivals, /sale, /search, /category/{slug}, /collections/{slug}
 * Filters, sorting and pagination come from the query string.
 */
$mode = $ROUTE['params']['mode'];
$filters = listing_filters_from_request();
$sort = array_key_exists(input('sort', '', 'get'), product_sort_options()) ? input('sort', '', 'get') : 'featured';
$perPage = max(8, min(60, (int) setting('products_per_page', 24)));
$scope = [];
$crumbs = [['Home', url()]];
$title = 'Shop All';
$intro = '';
$banner = null;
$subcats = [];
$category = $collection = null;
$noindex = false;
$canonicalPath = $mode;

switch ($mode) {
    case 'category':
        $category = category_by_slug($ROUTE['params']['slug']);
        if (!$category) {
            route_not_found($ROUTE['path']);
        }
        $ids = category_descendant_ids((int) $category['id']);
        $scope['category_ids'] = $ids;
        foreach (category_ancestors($category) as $a) {
            $crumbs[] = [$a['name'], category_url($a)];
        }
        $crumbs[] = [$category['name'], category_url($category)];
        $title = $category['name'];
        $intro = $category['description'];
        $banner = $category['banner_image'];
        $subcats = category_children((int) $category['id']);
        if (!$subcats && $category['parent_id']) {
            $subcats = category_children((int) $category['parent_id']); // siblings for quick switching
        }
        $noindex = (bool) $category['noindex'];
        $canonicalPath = 'category/' . $category['slug'];
        seo_set([
            'title' => $category['seo_title'] ?: $category['name'] . " — Men's " . $category['name'],
            'description' => $category['meta_description'] ?: str_limit($category['description'], 160),
            'image' => $category['og_image'] ?: ($category['banner_image'] ?: $category['image']),
        ]);
        break;
    case 'collection':
        $slug = $ROUTE['params']['slug'];
        $collection = collection_by_slug($slug);
        if (!$collection && $slug === 'new-arrivals') {
            $mode = 'new-arrivals';
            $filters['is_new'] = true;
            $title = 'New Arrivals';
            $canonicalPath = 'new-arrivals';
            break;
        }
        if (!$collection) {
            route_not_found($ROUTE['path']);
        }
        $scope['collection_id'] = (int) $collection['id'];
        $crumbs[] = ['Collections', url('collections')];
        $crumbs[] = [$collection['name'], collection_url($collection)];
        $title = $collection['name'];
        $intro = $collection['description'];
        $banner = $collection['banner_image'] ?: $collection['image'];
        $noindex = (bool) $collection['noindex'];
        $canonicalPath = 'collections/' . $collection['slug'];
        seo_set(['title' => $collection['seo_title'] ?: $collection['name'], 'description' => $collection['meta_description'] ?: str_limit($collection['description'], 160), 'image' => $banner]);
        break;
    case 'new-arrivals':
        $filters['is_new'] = true;
        $title = 'New Arrivals';
        $intro = 'The latest additions to the Urban Collections wardrobe.';
        if ($sort === 'featured') {
            $sort = 'newest';
        }
        break;
    case 'sale':
        $filters['on_sale'] = true;
        $title = 'Sale';
        $intro = 'Selected styles at special prices — while stock lasts.';
        break;
    case 'search':
        $title = $filters['q'] !== '' ? 'Search results for “' . $filters['q'] . '”' : 'Search';
        $noindex = true;
        break;
    default:
        $title = 'Shop All';
        $intro = "Modern menswear, footwear and accessories — curated for confidence and everyday style.";
}
if (in_array($mode, ['shop', 'new-arrivals', 'sale', 'search'], true)) {
    $crumbs[] = [$title, url($canonicalPath)];
    if (!seo_get('title')) {
        seo_set(['title' => $title, 'description' => $intro ?: null]);
    }
}
// Filtered / sorted / paginated variants are not separate pages for search engines
$hasFilters = $filters['sizes'] || $filters['colors'] || $filters['min_price'] !== null || $filters['max_price'] !== null || $filters['in_stock']
    || !empty($filters['subcategory']) || array_filter(array_intersect_key($filters, filter_attribute_columns()));
if ($hasFilters) {
    $noindex = true;
}
$page = max(1, input_int('page', 1));
seo_set([
    'canonical' => url($canonicalPath) . ($page > 1 ? '?page=' . $page : ''),
    'noindex' => $noindex || isset($_GET['sort']),
    'breadcrumbs' => $crumbs,
]);

$criteria = array_merge($filters, $scope);
if (!empty($filters['subcategory']) && $category) {
    $sub = categories_all()[(int) $filters['subcategory']] ?? null;
    if ($sub && in_array((int) $sub['id'], $scope['category_ids'], true)) {
        $criteria['category_ids'] = category_descendant_ids((int) $sub['id']);
    }
}
$total = products_search($criteria, $sort, 1, 0)['total'];
$pg = paginate($total, $perPage, $page);
$result = products_search($criteria, $sort, $perPage, $pg['offset']);
$facets = listing_facets($scope);

// Active filter chips
$chips = [];
foreach (['sizes' => 'size', 'colors' => 'color'] as $k => $param) {
    foreach ($filters[$k] as $v) {
        $chips[] = [$v, $param, $v];
    }
}
foreach (array_keys(filter_attribute_columns()) as $col) {
    foreach ($filters[$col] as $v) {
        $chips[] = [$v, $col, $v];
    }
}
if ($filters['min_price'] !== null || $filters['max_price'] !== null) {
    $chips[] = ['Price: ' . money($filters['min_price'] ?? 0) . ' – ' . ($filters['max_price'] !== null ? money($filters['max_price']) : 'any'), 'price', null];
}
if ($filters['in_stock'] && $mode !== 'search') {
    $chips[] = ['In stock', 'in_stock', null];
}
if (!empty($_GET['on_sale']) && $mode !== 'sale') {
    $chips[] = ['On sale', 'on_sale', null];
}
$baseUrl = url($mode === 'search' ? 'search' : $canonicalPath);

seo_add_schema([
    '@context' => 'https://schema.org', '@type' => 'ItemList',
    'itemListElement' => array_map(fn($p, $i) => ['@type' => 'ListItem', 'position' => $i + 1 + $pg['offset'], 'url' => $p['url']], $result['items'], array_keys($result['items'])),
]);
$bodyClass = 'page-listing';
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-listing-hero<?= $banner ? ' has-banner' : '' ?>">
    <?php if ($banner): ?>
        <div class="uc-listing-banner" data-parallax="0.2"><img src="<?= e(img_sized($banner, 1920)) ?>" alt="<?= e($title) ?>" fetchpriority="high"></div>
    <?php endif; ?>
    <div class="uc-container uc-listing-hero-inner">
        <?= breadcrumbs_html($crumbs) ?>
        <h1 class="uc-page-title"><?= e($title) ?></h1>
        <?php if ($intro): ?><p class="uc-page-intro"><?= e($intro) ?></p><?php endif; ?>
    </div>
</section>

<?php if ($subcats): ?>
<div class="uc-container">
    <nav class="uc-subcats" aria-label="Subcategories">
        <?php if ($category && !category_children((int) $category['id']) && $category['parent_id']):
            $parent = categories_all()[(int) $category['parent_id']] ?? null; ?>
            <?php if ($parent): ?><a href="<?= e(category_url($parent)) ?>">All <?= e($parent['name']) ?></a><?php endif; ?>
        <?php else: ?>
            <a href="<?= e(category_url($category)) ?>" class="is-active">All</a>
        <?php endif; ?>
        <?php foreach ($subcats as $sc): ?>
            <a href="<?= e(category_url($sc)) ?>"<?= (int) $sc['id'] === (int) $category['id'] ? ' class="is-active"' : '' ?>><?= e($sc['name']) ?></a>
        <?php endforeach; ?>
    </nav>
</div>
<?php endif; ?>

<div class="uc-container uc-listing" data-listing>
    <div class="uc-toolbar">
        <button type="button" class="uc-btn uc-btn--outline uc-btn--sm" data-drawer-open="ucFilters"><i class="bi bi-sliders"></i> Filter<?= $chips ? ' (' . count($chips) . ')' : '' ?></button>
        <p class="uc-result-count mb-0"><?= number_format($total) ?> <?= $total === 1 ? 'product' : 'products' ?></p>
        <form method="get" action="<?= e($baseUrl) ?>" class="uc-sort" data-sort-form>
            <?php foreach ($_GET as $k => $v): if (in_array($k, ['sort', 'page', 'route'], true)) continue; ?>
                <?php foreach ((array) $v as $vv): if (!is_scalar($vv)) continue; ?><input type="hidden" name="<?= e($k) . (is_array($v) ? '[]' : '') ?>" value="<?= e($vv) ?>"><?php endforeach; ?>
            <?php endforeach; ?>
            <label for="ucSort" class="visually-hidden">Sort by</label>
            <select id="ucSort" name="sort" class="form-select form-select-sm" data-auto-submit>
                <?php foreach (product_sort_options() as $k => $l): ?>
                    <option value="<?= e($k) ?>"<?= $sort === $k ? ' selected' : '' ?>><?= e($l) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($chips): ?>
        <div class="uc-chips">
            <?php foreach ($chips as [$label, $param, $value]):
                $q = $_GET;
                unset($q['page'], $q['route']);
                if ($param === 'price') {
                    unset($q['min_price'], $q['max_price']);
                } elseif ($value === null) {
                    unset($q[$param]);
                } else {
                    $q[$param] = array_values(array_diff((array) ($q[$param] ?? []), [$value]));
                }
                $q = array_filter($q, fn($v) => $v !== '' && $v !== []);
            ?>
                <a class="uc-chip" href="<?= e($baseUrl . ($q ? '?' . http_build_query($q) : '')) ?>"><?= e($label) ?> <i class="bi bi-x"></i></a>
            <?php endforeach; ?>
            <a class="uc-chip uc-chip--clear" href="<?= e($baseUrl . ($mode === 'search' ? '?q=' . urlencode($filters['q']) : '')) ?>">Clear all</a>
        </div>
    <?php endif; ?>

    <div class="uc-listing-body">
        <aside class="uc-filters uc-drawer uc-drawer--left" id="ucFilters" aria-label="Filters" aria-hidden="true">
            <div class="uc-drawer-head">
                <span class="uc-drawer-title">Filter</span>
                <button class="uc-icon-btn" type="button" data-drawer-close aria-label="Close filters"><i class="bi bi-x-lg"></i></button>
            </div>
            <form method="get" action="<?= e($baseUrl) ?>" class="uc-drawer-body" data-filter-form>
                <?php if ($mode === 'search'): ?><input type="hidden" name="q" value="<?= e($filters['q']) ?>"><?php endif; ?>
                <?php if ($sort !== 'featured'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
                <?php partial('components/filters', ['facets' => $facets, 'filters' => $filters, 'category' => $category]); ?>
                <div class="uc-filter-actions">
                    <a class="uc-btn uc-btn--outline uc-btn--sm" href="<?= e($baseUrl . ($mode === 'search' ? '?q=' . urlencode($filters['q']) : '')) ?>">Reset</a>
                    <button class="uc-btn uc-btn--sm" type="submit">Show results</button>
                </div>
            </form>
        </aside>

        <div class="uc-listing-results" data-listing-results>
            <?php if ($result['items']): ?>
                <div class="uc-grid uc-grid--listing">
                    <?php foreach ($result['items'] as $i => $p): ?>
                        <?php partial('components/product-card', ['p' => $p, 'lazy' => $i > 3]); ?>
                    <?php endforeach; ?>
                </div>
                <?= pagination_links($pg, $baseUrl) ?>
            <?php else: ?>
                <div class="uc-empty">
                    <i class="bi bi-search"></i>
                    <h2>No products found</h2>
                    <p>Try removing some filters or searching for something else.</p>
                    <a class="uc-btn" href="<?= e(url('shop')) ?>">Browse all products</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require UC_ROOT . '/includes/footer.php';
