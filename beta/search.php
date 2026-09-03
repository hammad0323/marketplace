<?php
require __DIR__ . '/config/config.php';

$q = trim($_GET['q'] ?? '');
$like = '%' . $q . '%';
$perPage = 12;

$categoryFilter = $_GET['category_id'] ?? '';
$shopFilter = $_GET['shop_id'] ?? '';
$minPrice = $_GET['min_price'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$sort = $_GET['sort'] ?? 'relevance';

$where = "p.status='active' AND s.status='active' AND (p.name LIKE ? OR p.short_description LIKE ? OR p.sku LIKE ?)";
$types = 'sss'; $params = [$like, $like, $like];

if ($categoryFilter !== '') { $where .= " AND p.category_id = ?"; $types .= 'i'; $params[] = (int)$categoryFilter; }
if ($shopFilter !== '') { $where .= " AND p.shop_id = ?"; $types .= 'i'; $params[] = (int)$shopFilter; }
if ($minPrice !== '') { $where .= " AND COALESCE(p.sale_price,p.regular_price) >= ?"; $types .= 'd'; $params[] = (float)$minPrice; }
if ($maxPrice !== '') { $where .= " AND COALESCE(p.sale_price,p.regular_price) <= ?"; $types .= 'd'; $params[] = (float)$maxPrice; }

$orderMap = ['newest' => 'p.created_at DESC', 'price_low' => 'price ASC', 'price_high' => 'price DESC', 'rating' => 'p.rating_avg DESC', 'popular' => 'p.total_sold DESC'];
$orderBy = $orderMap[$sort] ?? 'p.rating_avg DESC, p.total_sold DESC';

$countRow = db_fetch_one("SELECT COUNT(*) c FROM products p JOIN shops s ON s.id=p.shop_id WHERE $where", $types, $params);
$pagination = paginate((int)$countRow['c'], $perPage);

$products = db_fetch_all("SELECT p.*, COALESCE(p.sale_price,p.regular_price) price, s.shop_name, s.slug as shop_slug
    FROM products p JOIN shops s ON s.id=p.shop_id WHERE $where ORDER BY $orderBy LIMIT ? OFFSET ?",
    $types . 'ii', [...$params, $perPage, $pagination['offset']]);

$matchedShops = $q ? db_fetch_all("SELECT * FROM shops WHERE status='active' AND shop_name LIKE ? LIMIT 4", 's', [$like]) : [];
$categories = db_fetch_all("SELECT * FROM categories WHERE status='active' ORDER BY name");

$pageTitle = $q ? 'Search: ' . $q : 'Search';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/product-card.php';
?>
<div class="container">
  <h1 class="page-title">Search results for "<?= clean($q) ?>"</h1>

  <?php if ($matchedShops): ?>
    <div class="section-head"><h3>Matching Shops</h3></div>
    <div class="shop-grid">
      <?php foreach ($matchedShops as $shop): ?>
        <a class="shop-card" href="<?= base_url('shop.php?slug=' . $shop['slug']) ?>">
          <img class="shop-card-logo" src="<?= shop_logo_or_default($shop['logo']) ?>" alt="">
          <h3><?= clean($shop['shop_name']) ?></h3>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="listing-layout">
    <aside class="listing-filters">
      <form method="get">
        <input type="hidden" name="q" value="<?= clean($q) ?>">
        <div class="filter-block">
          <h4>Category</h4>
          <select name="category_id" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>" <?= (string)$categoryFilter === (string)$c['id'] ? 'selected' : '' ?>><?= clean($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-block">
          <h4>Price Range</h4>
          <input type="number" name="min_price" placeholder="Min" value="<?= clean($minPrice) ?>">
          <input type="number" name="max_price" placeholder="Max" value="<?= clean($maxPrice) ?>">
          <button type="submit" class="btn btn-sm btn-outline btn-block">Apply Filters</button>
        </div>
      </form>
    </aside>
    <div class="listing-main">
      <div class="listing-toolbar">
        <span><?= $pagination['totalRows'] ?> results</span>
        <select onchange="var u=new URL(location.href);u.searchParams.set('sort',this.value);location.href=u;">
          <?php foreach (['relevance' => 'Relevance', 'newest' => 'Newest', 'price_low' => 'Price: Low to High', 'price_high' => 'Price: High to Low', 'rating' => 'Rating', 'popular' => 'Best Selling'] as $k => $v): ?>
            <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($products): ?>
        <div class="product-grid"><?php foreach ($products as $p) render_product_card($p); ?></div>
        <?= pagination_links($pagination, base_url('search.php?q=' . urlencode($q))) ?>
      <?php else: ?>
        <div class="empty-state"><i class="fa-solid fa-magnifying-glass"></i><h3>No results found</h3><p>Try a different search term.</p></div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
