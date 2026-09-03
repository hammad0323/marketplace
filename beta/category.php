<?php
require __DIR__ . '/config/config.php';

$slug = $_GET['slug'] ?? '';
$category = db_fetch_one("SELECT * FROM categories WHERE slug = ? AND status='active'", 's', [$slug]);
if (!$category) {
    $subcategory = db_fetch_one("SELECT sc.*, c.slug as parent_slug, c.name as parent_name FROM subcategories sc
                                  JOIN categories c ON c.id = sc.category_id WHERE sc.slug = ? AND sc.status='active'", 's', [$slug]);
    if (!$subcategory) { http_response_code(404); require __DIR__ . '/404.php'; exit; }
}

$categoryId = $category['id'] ?? $subcategory['category_id'];
$subcategories = db_fetch_all("SELECT * FROM subcategories WHERE category_id=? AND status='active' ORDER BY sort_order", 'i', [$categoryId]);

$perPage = 12;
$where = isset($subcategory) ? "p.subcategory_id = ?" : "p.category_id = ?";
$filterId = isset($subcategory) ? $subcategory['id'] : $categoryId;

$sort = $_GET['sort'] ?? 'newest';
$orderMap = ['newest' => 'p.created_at DESC', 'price_low' => 'price ASC', 'price_high' => 'price DESC', 'popular' => 'p.total_sold DESC', 'rating' => 'p.rating_avg DESC'];
$orderBy = $orderMap[$sort] ?? 'p.created_at DESC';

$minPrice = $_GET['min_price'] ?? null;
$maxPrice = $_GET['max_price'] ?? null;
$extraWhere = '';
$extraParams = []; $extraTypes = '';
if ($minPrice !== null && $minPrice !== '') { $extraWhere .= " AND COALESCE(p.sale_price, p.regular_price) >= ?"; $extraParams[] = (float)$minPrice; $extraTypes .= 'd'; }
if ($maxPrice !== null && $maxPrice !== '') { $extraWhere .= " AND COALESCE(p.sale_price, p.regular_price) <= ?"; $extraParams[] = (float)$maxPrice; $extraTypes .= 'd'; }

$countRow = db_fetch_one("SELECT COUNT(*) c FROM products p WHERE $where AND p.status='active' $extraWhere", 'i' . $extraTypes, [$filterId, ...$extraParams]);
$pagination = paginate((int)$countRow['c'], $perPage);

$products = db_fetch_all("SELECT p.*, COALESCE(p.sale_price,p.regular_price) as price, s.shop_name, s.slug as shop_slug
                           FROM products p JOIN shops s ON s.id = p.shop_id
                           WHERE $where AND p.status='active' AND s.status='active' $extraWhere
                           ORDER BY $orderBy LIMIT ? OFFSET ?",
    'i' . $extraTypes . 'ii', [$filterId, ...$extraParams, $perPage, $pagination['offset']]);

$pageTitle = $category['name'] ?? $subcategory['name'];
$seoEntityType = isset($subcategory) ? 'subcategory' : 'category';
$seoEntityId = $filterId;
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/product-card.php';
?>
<div class="container">
  <div class="breadcrumb">
    <a href="<?= base_url() ?>">Home</a> /
    <?php if (isset($subcategory)): ?>
      <a href="<?= base_url('category.php?slug=' . $subcategory['parent_slug']) ?>"><?= clean($subcategory['parent_name']) ?></a> / <?= clean($subcategory['name']) ?>
    <?php else: ?>
      <?= clean($category['name']) ?>
    <?php endif; ?>
  </div>
  <h1 class="page-title"><?= clean($category['name'] ?? $subcategory['name']) ?></h1>
  <div class="listing-layout">
    <aside class="listing-filters">
      <?php if ($subcategories): ?>
      <div class="filter-block">
        <h4>Subcategories</h4>
        <ul>
          <?php foreach ($subcategories as $sc): ?>
            <li><a href="<?= base_url('category.php?slug=' . $sc['slug']) ?>"><?= clean($sc['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
      <form class="filter-block" method="get">
        <input type="hidden" name="slug" value="<?= clean($slug) ?>">
        <h4>Price Range</h4>
        <input type="number" name="min_price" placeholder="Min" value="<?= clean($minPrice ?? '') ?>">
        <input type="number" name="max_price" placeholder="Max" value="<?= clean($maxPrice ?? '') ?>">
        <button type="submit" class="btn btn-sm btn-outline btn-block">Apply</button>
      </form>
    </aside>
    <div class="listing-main">
      <div class="listing-toolbar">
        <span><?= $pagination['totalRows'] ?> products found</span>
        <form method="get" class="sort-form">
          <input type="hidden" name="slug" value="<?= clean($slug) ?>">
          <select name="sort" onchange="this.form.submit()">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
            <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
            <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Best Selling</option>
            <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Top Rated</option>
          </select>
        </form>
      </div>
      <?php if ($products): ?>
        <div class="product-grid">
          <?php foreach ($products as $p) render_product_card($p); ?>
        </div>
        <?= pagination_links($pagination, base_url('category.php?slug=' . $slug . '&sort=' . $sort)) ?>
      <?php else: ?>
        <div class="empty-state">
          <i class="fa-solid fa-box-open"></i>
          <h3>No Products Found</h3>
          <p>There are no products in this category yet.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
