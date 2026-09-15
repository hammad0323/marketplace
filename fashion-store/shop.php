<?php
require_once __DIR__ . '/includes/functions.php';

$categorySlug = $_GET['category'] ?? '';
$brandFilter = (int)($_GET['brand'] ?? 0);
$sizeFilter = $_GET['size'] ?? '';
$colorFilter = $_GET['color'] ?? '';
$minPrice = $_GET['min_price'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$saleOnly = !empty($_GET['sale']);
$sort = $_GET['sort'] ?? 'latest';
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;

$category = null;
if ($categorySlug) {
    $stmt = mysqli_prepare($mysqli, "SELECT * FROM categories WHERE slug = ?");
    mysqli_stmt_bind_param($stmt, 's', $categorySlug);
    mysqli_stmt_execute($stmt);
    $category = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

$where = ["p.status = 'active'"];
$types = '';
$params = [];

if ($category) {
    $childIds = [$category['id']];
    $childRes = mysqli_query($mysqli, "SELECT id FROM categories WHERE parent_id = " . (int)$category['id']);
    while ($c = mysqli_fetch_assoc($childRes)) $childIds[] = $c['id'];
    $where[] = 'p.category_id IN (' . implode(',', array_map('intval', $childIds)) . ')';
}
if ($brandFilter) { $where[] = 'p.brand_id = ?'; $types .= 'i'; $params[] = $brandFilter; }
if ($minPrice !== '') { $where[] = 'COALESCE(p.sale_price, p.regular_price) >= ?'; $types .= 'd'; $params[] = (float)$minPrice; }
if ($maxPrice !== '') { $where[] = 'COALESCE(p.sale_price, p.regular_price) <= ?'; $types .= 'd'; $params[] = (float)$maxPrice; }
if ($saleOnly) { $where[] = 'p.sale_price IS NOT NULL AND p.sale_price < p.regular_price'; }
if ($q !== '') { $where[] = "(p.name LIKE CONCAT('%',?,'%') OR p.tags LIKE CONCAT('%',?,'%'))"; $types .= 'ss'; $params[] = $q; $params[] = $q; }
if ($sizeFilter) {
    $where[] = "p.id IN (SELECT pv.product_id FROM product_variations pv JOIN product_variation_values pvv ON pvv.variation_id = pv.id JOIN attribute_values av ON av.id = pvv.attribute_value_id WHERE av.value = ?)";
    $types .= 's'; $params[] = $sizeFilter;
}
if ($colorFilter) {
    $where[] = "p.id IN (SELECT pv.product_id FROM product_variations pv JOIN product_variation_values pvv ON pvv.variation_id = pv.id JOIN attribute_values av ON av.id = pvv.attribute_value_id WHERE av.value = ?)";
    $types .= 's'; $params[] = $colorFilter;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$orderBy = 'p.created_at DESC';
if ($sort === 'price_low') $orderBy = 'COALESCE(p.sale_price, p.regular_price) ASC';
elseif ($sort === 'price_high') $orderBy = 'COALESCE(p.sale_price, p.regular_price) DESC';
elseif ($sort === 'popular') $orderBy = 'p.views DESC';
elseif ($sort === 'best_selling') $orderBy = 'p.is_best_seller DESC, p.created_at DESC';

$countStmt = mysqli_prepare($mysqli, "SELECT COUNT(*) c FROM products p $whereSql");
if ($types) mysqli_stmt_bind_param($countStmt, $types, ...$params);
mysqli_stmt_execute($countStmt);
$total = mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['c'];
$totalPages = max(1, ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$sql = "SELECT p.* FROM products p $whereSql ORDER BY $orderBy LIMIT $perPage OFFSET $offset";
$stmt = mysqli_prepare($mysqli, $sql);
if ($types) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$products = mysqli_stmt_get_result($stmt);

$brands = mysqli_query($mysqli, "SELECT * FROM brands WHERE status = 'active' ORDER BY name");
$sizes = mysqli_query($mysqli, "SELECT DISTINCT value FROM attribute_values WHERE attribute_id = (SELECT id FROM attributes WHERE name='Size' LIMIT 1)");
$colors = mysqli_query($mysqli, "SELECT DISTINCT value FROM attribute_values WHERE attribute_id = (SELECT id FROM attributes WHERE name='Color' LIMIT 1)");

$pageTitle = ($category ? $category['seo_title'] ?: $category['name'] : 'Shop All') . ' | ' . get_setting('store_name');
require_once __DIR__ . '/includes/header.php';
?>
<div class="container section-tight">
  <nav class="small text-muted mb-3"><a href="<?= BASE_URL ?>/index.php">Home</a> / <span><?= e($category['name'] ?? 'Shop') ?></span></nav>
  <div class="row g-4">
    <div class="col-lg-3">
      <form class="filter-sidebar" method="get" id="filterForm">
        <?php if ($categorySlug): ?><input type="hidden" name="category" value="<?= e($categorySlug) ?>"><?php endif; ?>
        <div class="filter-group">
          <h4>Price Range</h4>
          <div class="d-flex gap-2">
            <input type="number" name="min_price" class="form-control form-control-sm" placeholder="Min" value="<?= e($minPrice) ?>">
            <input type="number" name="max_price" class="form-control form-control-sm" placeholder="Max" value="<?= e($maxPrice) ?>">
          </div>
        </div>
        <?php if (mysqli_num_rows($brands)): ?>
        <div class="filter-group">
          <h4>Brand</h4>
          <select name="brand" class="form-select form-select-sm">
            <option value="">All Brands</option>
            <?php mysqli_data_seek($brands, 0); while ($b = mysqli_fetch_assoc($brands)): ?>
              <option value="<?= (int)$b['id'] ?>" <?= $brandFilter==$b['id']?'selected':'' ?>><?= e($b['name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="filter-group">
          <h4>Size</h4>
          <div class="d-flex flex-wrap gap-2">
            <?php mysqli_data_seek($sizes, 0); while ($s = mysqli_fetch_assoc($sizes)): ?>
              <label class="size-swatch <?= $sizeFilter===$s['value']?'active':'' ?>">
                <input type="radio" name="size" value="<?= e($s['value']) ?>" class="d-none" <?= $sizeFilter===$s['value']?'checked':'' ?> onchange="this.form.submit()"><?= e($s['value']) ?>
              </label>
            <?php endwhile; ?>
          </div>
        </div>
        <div class="filter-group">
          <h4>Availability</h4>
          <div class="form-check"><input type="checkbox" class="form-check-input" name="sale" value="1" id="saleOnly" <?= $saleOnly?'checked':'' ?> onchange="this.form.submit()"><label class="form-check-label" for="saleOnly">On Sale</label></div>
        </div>
        <button class="btn btn-outline-brand btn-sm w-100">Apply Filters</button>
        <a href="<?= BASE_URL ?>/shop.php<?= $categorySlug ? '?category='.e($categorySlug) : '' ?>" class="btn btn-link btn-sm w-100 mt-1">Clear Filters</a>
      </form>
    </div>
    <div class="col-lg-9">
      <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h4 font-serif mb-0"><?= e($category['name'] ?? 'Shop All') ?> <span class="text-muted small">(<?= (int)$total ?> products)</span></h1>
        <form method="get" id="sortForm">
          <?php foreach ($_GET as $k => $v) if ($k !== 'sort') echo '<input type="hidden" name="'.e($k).'" value="'.e($v).'">'; ?>
          <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="latest" <?= $sort==='latest'?'selected':'' ?>>Latest</option>
            <option value="price_low" <?= $sort==='price_low'?'selected':'' ?>>Price: Low to High</option>
            <option value="price_high" <?= $sort==='price_high'?'selected':'' ?>>Price: High to Low</option>
            <option value="popular" <?= $sort==='popular'?'selected':'' ?>>Popular</option>
            <option value="best_selling" <?= $sort==='best_selling'?'selected':'' ?>>Best Selling</option>
          </select>
        </form>
      </div>

      <?php if (mysqli_num_rows($products) === 0): ?>
        <p class="text-muted py-5 text-center">No products found matching your filters.</p>
      <?php else: ?>
      <div class="row row-cols-2 row-cols-md-3 g-3 g-md-4">
        <?php while ($p = mysqli_fetch_assoc($products)): ?>
          <div class="col"><?php render_product_card($mysqli, $p); ?></div>
        <?php endwhile; ?>
      </div>
      <?php if ($totalPages > 1): ?>
      <nav class="mt-4"><ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $totalPages; $i++):
          $qs = $_GET; $qs['page'] = $i; ?>
          <li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="?<?= http_build_query($qs) ?>"><?= $i ?></a></li>
        <?php endfor; ?>
      </ul></nav>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
