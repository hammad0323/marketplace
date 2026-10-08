<?php
/**
 * Listing page:  /shop  ·  /category/{slug}  ·  /category/{slug}/{sub}  ·  /shop?q=search
 */
require __DIR__ . '/includes/bootstrap.php';

$cat = $sub = null;
if (get('cat') !== '') {
    $cat = row('SELECT * FROM categories WHERE slug = ? AND status = 1 AND parent_id IS NULL', [get('cat')]);
    if (!$cat) not_found();
    if (get('sub') !== '') {
        $sub = row('SELECT * FROM categories WHERE slug = ? AND status = 1 AND parent_id = ?', [get('sub'), $cat['id']]);
        if (!$sub) not_found();
    }
}

$where = ['1'];
$params = [];
if ($sub) {
    $where[] = 'p.subcategory_id = ?';
    $params[] = $sub['id'];
} elseif ($cat) {
    $where[] = 'p.category_id = ?';
    $params[] = $cat['id'];
}

$q = mb_substr(get('q'), 0, 80);
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.fabric LIKE ? OR p.short_description LIKE ? OR c.name LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%");
}

$filter = get('filter');
$filters = ['new' => 'New Arrivals', 'sale' => 'Sale', 'bestseller' => 'Best Sellers', 'featured' => 'Featured'];
switch ($filter) {
    case 'new':        $where[] = 'p.is_new = 1'; break;
    case 'bestseller': $where[] = 'p.is_bestseller = 1'; break;
    case 'featured':   $where[] = 'p.is_featured = 1'; break;
    case 'sale':       $where[] = 'p.sale_price > 0 AND p.sale_price < p.price'; break;
}

$priceExpr = 'IF(p.sale_price > 0 AND p.sale_price < p.price, p.sale_price, p.price)';
if (get('min') !== '' && is_numeric(get('min'))) { $where[] = "$priceExpr >= ?"; $params[] = (float)get('min'); }
if (get('max') !== '' && is_numeric(get('max'))) { $where[] = "$priceExpr <= ?"; $params[] = (float)get('max'); }
if (get('size') !== '') { $where[] = 'FIND_IN_SET(?, REPLACE(p.sizes, ", ", ","))'; $params[] = get('size'); }
if (get('stock') === '1') { $where[] = 'p.stock > 0'; }

$sorts = [
    'latest'     => ['Newest', 'p.created_at DESC'],
    'popular'    => ['Popularity', 'p.sales_count DESC'],
    'price_asc'  => ['Price: Low to High', "$priceExpr ASC"],
    'price_desc' => ['Price: High to Low', "$priceExpr DESC"],
    'name'       => ['Name A–Z', 'p.name ASC'],
];
$sort = isset($sorts[get('sort')]) ? get('sort') : 'latest';

$whereSql = implode(' AND ', $where);
$total = (int)val("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.status = 1 AND $whereSql", $params);
$pg = paginate($total, 16, (int)get('page', 1));
$products = product_list($whereSql, $params, $sorts[$sort][1], $pg['per'], $pg['offset']);

$heading = $sub['name'] ?? $cat['name'] ?? ($filters[$filter] ?? ($q !== '' ? 'Search: “' . $q . '”' : 'Shop All'));
$active = $sub ?: $cat;
$children = $cat ? rows('SELECT * FROM categories WHERE parent_id = ? AND status = 1 ORDER BY sort_order', [$cat['id']]) : [];
$banner = ($sub['banner'] ?? '') ?: ($cat['banner'] ?? '') ?: 'assets/images/demo/banner-2.svg';
$allSizes = [];
foreach (rows('SELECT DISTINCT sizes FROM products WHERE status = 1 AND sizes <> ""') as $r) {
    foreach (explode(',', $r['sizes']) as $s) { $s = trim($s); if ($s !== '') $allSizes[$s] = $s; }
}

$seo = [
    'title'       => ($active['meta_title'] ?? '') ?: $heading . ' | ' . setting('site_name'),
    'description' => ($active['meta_description'] ?? '') ?: (($active['description'] ?? '') ?: 'Shop ' . $heading . ' online at ' . setting('site_name') . '. Cash on Delivery available across Pakistan.'),
    'keywords'    => ($active['meta_keywords'] ?? '') ?: setting('meta_keywords'),
    'canonical'   => $active ? site_url() . substr(category_url($active), strlen(base_path())) : abs_url('shop'),
    'noindex'     => $q !== '',
];
$crumbs = [['Home', abs_url('')]];
if ($cat) $crumbs[] = [$cat['name'], site_url() . substr(category_url($cat), strlen(base_path()))];
if ($sub) $crumbs[] = [$sub['name'], site_url() . substr(category_url($sub), strlen(base_path()))];
$seo['schema'][] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => array_map(fn($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1]], $crumbs, array_keys($crumbs))];

$wish = wishlist_ids();
require ROOT . '/includes/header.php';
?>
<section class="page-hero" style="--bh:360px">
  <div class="page-hero__bg" data-parallax="0.3" style="background-image:url('<?= e(img($banner)) ?>')"></div>
  <div class="page-hero__overlay"></div>
  <div class="container page-hero__content">
    <nav class="breadcrumbs" data-reveal>
      <a href="<?= url('') ?>">Home</a><span>/</span>
      <?php if ($cat): ?><a href="<?= category_url($cat) ?>"><?= e($cat['name']) ?></a><?php if ($sub): ?><span>/</span><span><?= e($sub['name']) ?></span><?php endif; ?>
      <?php else: ?><span>Shop</span><?php endif; ?>
    </nav>
    <h1 class="display" data-reveal data-delay="1"><?= e($heading) ?></h1>
    <?php if (!empty($active['description'])): ?><p data-reveal data-delay="2"><?= e(excerpt($active['description'], 180)) ?></p><?php endif; ?>
  </div>
</section>

<section class="section section--tight">
  <div class="container">
    <?php if ($children): ?>
      <div class="chips" data-reveal>
        <a class="chip<?= !$sub ? ' active' : '' ?>" href="<?= category_url($cat) ?>">All</a>
        <?php foreach ($children as $ch): ?><a class="chip<?= ($sub && $sub['id'] == $ch['id']) ? ' active' : '' ?>" href="<?= category_url($ch) ?>"><?= e($ch['name']) ?></a><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="shop-layout">
      <aside class="filters" id="filters">
        <form method="get" class="filters__form">
          <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
          <input type="hidden" name="sort" value="<?= e($sort) ?>">
          <div class="filters__head"><h3>Filter</h3><button type="button" class="icon-btn only-mobile" data-toggle-filters><?= icon('close', 20) ?></button></div>
          <?php if (!$cat): ?>
          <div class="filter-group">
            <h4>Category</h4>
            <?php foreach (category_tree() as $tc): ?><a href="<?= category_url($tc) ?>"><?= e($tc['name']) ?></a><?php endforeach; ?>
          </div>
          <?php endif; ?>
          <div class="filter-group">
            <h4>Collection</h4>
            <label><input type="radio" name="filter" value="" <?= $filter === '' ? 'checked' : '' ?>> All</label>
            <?php foreach ($filters as $k => $lbl): ?><label><input type="radio" name="filter" value="<?= $k ?>" <?= $filter === $k ? 'checked' : '' ?>> <?= $lbl ?></label><?php endforeach; ?>
          </div>
          <?php if ($allSizes): ?>
          <div class="filter-group">
            <h4>Size</h4>
            <div class="size-pills">
              <label><input type="radio" name="size" value="" <?= get('size') === '' ? 'checked' : '' ?>><span>Any</span></label>
              <?php foreach ($allSizes as $s): ?><label><input type="radio" name="size" value="<?= e($s) ?>" <?= get('size') === $s ? 'checked' : '' ?>><span><?= e($s) ?></span></label><?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
          <div class="filter-group">
            <h4>Price (<?= e(setting('currency', 'Rs.')) ?>)</h4>
            <div class="price-range"><input type="number" name="min" placeholder="Min" value="<?= e(get('min')) ?>" min="0"><span>–</span><input type="number" name="max" placeholder="Max" value="<?= e(get('max')) ?>" min="0"></div>
          </div>
          <div class="filter-group"><label><input type="checkbox" name="stock" value="1" <?= get('stock') === '1' ? 'checked' : '' ?>> In stock only</label></div>
          <button class="btn btn-dark btn-block" type="submit">Apply Filters</button>
          <a class="link-underline mt-20" href="<?= $active ? category_url($active) : url('shop') ?>">Clear all</a>
        </form>
      </aside>

      <div class="shop-main">
        <div class="toolbar">
          <button class="btn btn-outline btn-sm only-mobile" type="button" data-toggle-filters><?= icon('filter', 16) ?> Filter</button>
          <span class="muted"><?= $total ?> <?= $total === 1 ? 'piece' : 'pieces' ?></span>
          <form method="get" class="sort-form">
            <?php foreach ($_GET as $k => $v): if (in_array($k, ['sort', 'page', 'cat', 'sub'], true) || !is_string($v)) continue; ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
            <label>Sort by
              <select name="sort" onchange="this.form.submit()">
                <?php foreach ($sorts as $k => [$lbl]): ?><option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?>
              </select>
            </label>
          </form>
        </div>
        <?php if ($products): ?>
          <div class="grid grid-3"><?php foreach ($products as $p) { require ROOT . '/includes/product-card.php'; } ?></div>
          <?= page_links($pg) ?>
        <?php else: ?>
          <div class="empty-state" data-reveal>
            <span class="ornament">✦</span>
            <h3>No pieces found</h3>
            <p>Try removing some filters or explore our latest arrivals.</p>
            <a class="btn btn-dark" href="<?= url('shop?filter=new') ?>">Shop New Arrivals</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php require ROOT . '/includes/footer.php';
