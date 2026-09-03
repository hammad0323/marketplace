<?php
require __DIR__ . '/config/config.php';

$slug = $_GET['slug'] ?? '';
$shop = db_fetch_one("SELECT * FROM shops WHERE slug = ?", 's', [$slug]);
if (!$shop || !in_array($shop['status'], ['active', 'payment_overdue'], true)) {
    http_response_code(404); require __DIR__ . '/404.php'; exit;
}

$tab = $_GET['tab'] ?? 'home';
$sections = $tab === 'home' ? db_fetch_all("SELECT * FROM shop_sections WHERE shop_id=? AND status='active' ORDER BY sort_order", 'i', [$shop['id']]) : [];

$perPage = 12;
$sortMap = ['newest' => 'p.created_at DESC', 'popular' => 'p.total_sold DESC', 'price_low' => 'price ASC', 'price_high' => 'price DESC'];
$sort = $_GET['sort'] ?? 'newest';
$orderBy = $sortMap[$sort] ?? 'p.created_at DESC';

$categoryFilter = $_GET['category_id'] ?? null;
$catWhere = $categoryFilter ? " AND p.category_id = " . (int)$categoryFilter : '';

if ($tab === 'products') {
    $countRow = db_fetch_one("SELECT COUNT(*) c FROM products p WHERE p.shop_id=? AND p.status='active'$catWhere", 'i', [$shop['id']]);
    $pagination = paginate((int)$countRow['c'], $perPage);
    $shopProducts = db_fetch_all("SELECT p.*, COALESCE(p.sale_price,p.regular_price) price FROM products p
        WHERE p.shop_id=? AND p.status='active'$catWhere ORDER BY $orderBy LIMIT ? OFFSET ?",
        'iii', [$shop['id'], $perPage, $pagination['offset']]);
}
$shopCategories = db_fetch_all("SELECT DISTINCT c.id, c.name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.shop_id=? AND p.status='active'", 'i', [$shop['id']]);
$reviews = db_fetch_all("SELECT r.*, c.first_name, c.last_name, p.name as product_name FROM reviews r
    JOIN customers c ON c.id = r.customer_id JOIN products p ON p.id = r.product_id
    WHERE p.shop_id=? AND r.status='approved' ORDER BY r.created_at DESC LIMIT 10", 'i', [$shop['id']]);

$seoEntityType = 'shop'; $seoEntityId = $shop['id']; $pageTitle = $shop['shop_name'];
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/product-card.php';
?>
<div class="shop-cover" style="background-image:url('<?= shop_cover_or_default($shop['cover_image']) ?>')"></div>
<div class="container">
  <div class="shop-header">
    <img class="shop-header-logo" src="<?= shop_logo_or_default($shop['logo']) ?>" alt="<?= clean($shop['shop_name']) ?>">
    <div class="shop-header-info">
      <h1><?= clean($shop['shop_name']) ?></h1>
      <p><?= clean($shop['description']) ?></p>
      <div class="shop-header-meta">
        <span><i class="fa-solid fa-star"></i> <?= number_format($shop['rating_avg'], 1) ?> (<?= $shop['rating_count'] ?> ratings)</span>
        <span><i class="fa-solid fa-location-dot"></i> <?= clean($shop['city']) ?>, <?= clean($shop['country']) ?></span>
        <span><i class="fa-solid fa-phone"></i> <?= clean($shop['phone']) ?></span>
      </div>
    </div>
    <?php if ($shop['status'] === 'payment_overdue'): ?>
      <div class="alert alert-error">This shop's purchasing is temporarily restricted.</div>
    <?php endif; ?>
  </div>

  <nav class="shop-tabs">
    <a class="<?= $tab === 'home' ? 'active' : '' ?>" href="<?= base_url('shop.php?slug=' . $slug) ?>">Home</a>
    <a class="<?= $tab === 'products' ? 'active' : '' ?>" href="<?= base_url('shop.php?slug=' . $slug . '&tab=products') ?>">Products</a>
    <a class="<?= $tab === 'about' ? 'active' : '' ?>" href="<?= base_url('shop.php?slug=' . $slug . '&tab=about') ?>">About</a>
    <a class="<?= $tab === 'reviews' ? 'active' : '' ?>" href="<?= base_url('shop.php?slug=' . $slug . '&tab=reviews') ?>">Reviews</a>
  </nav>

  <?php if ($tab === 'home'): ?>
    <?php foreach ($sections as $section):
        $sectionProducts = db_fetch_all("SELECT p.* FROM shop_section_products ssp JOIN products p ON p.id = ssp.product_id
            WHERE ssp.shop_section_id = ? AND p.status='active' ORDER BY ssp.sort_order", 'i', [$section['id']]);
        if (!$sectionProducts) {
            $fallbackOrder = $section['section_type'] === 'best_selling' ? 'total_sold DESC' : 'created_at DESC';
            $sectionProducts = db_fetch_all("SELECT * FROM products WHERE shop_id=? AND status='active' ORDER BY $fallbackOrder LIMIT 8", 'i', [$shop['id']]);
        }
        if (!$sectionProducts) continue; ?>
        <section class="section">
          <div class="section-head"><h2><?= clean($section['heading']) ?></h2></div>
          <div class="product-grid"><?php foreach ($sectionProducts as $p) { $p['shop_name'] = $shop['shop_name']; $p['shop_slug'] = $shop['slug']; render_product_card($p); } ?></div>
        </section>
    <?php endforeach; ?>
    <?php if (!$sections): ?><div class="empty-state"><i class="fa-solid fa-store"></i><h3>Shop is being set up</h3></div><?php endif; ?>

  <?php elseif ($tab === 'products'): ?>
    <div class="listing-layout">
      <aside class="listing-filters">
        <div class="filter-block">
          <h4>Categories</h4>
          <ul>
            <li><a href="<?= base_url('shop.php?slug=' . $slug . '&tab=products') ?>">All</a></li>
            <?php foreach ($shopCategories as $c): ?>
              <li><a href="<?= base_url('shop.php?slug=' . $slug . '&tab=products&category_id=' . $c['id']) ?>"><?= clean($c['name']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </aside>
      <div class="listing-main">
        <div class="listing-toolbar">
          <span><?= $pagination['totalRows'] ?> products</span>
          <select onchange="location.href=this.value">
            <?php foreach (['newest' => 'Newest', 'popular' => 'Best Selling', 'price_low' => 'Price: Low to High', 'price_high' => 'Price: High to Low'] as $k => $v): ?>
              <option value="<?= base_url('shop.php?slug=' . $slug . '&tab=products&sort=' . $k) ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if ($shopProducts): ?>
          <div class="product-grid"><?php foreach ($shopProducts as $p) { $p['shop_name'] = $shop['shop_name']; $p['shop_slug'] = $shop['slug']; render_product_card($p); } ?></div>
          <?= pagination_links($pagination, base_url('shop.php?slug=' . $slug . '&tab=products')) ?>
        <?php else: ?>
          <div class="empty-state"><i class="fa-solid fa-box-open"></i><h3>No Products Found</h3></div>
        <?php endif; ?>
      </div>
    </div>

  <?php elseif ($tab === 'about'): ?>
    <div class="shop-about"><?= $shop['description'] ?></div>

  <?php elseif ($tab === 'reviews'): ?>
    <?php if ($reviews): foreach ($reviews as $r): ?>
      <div class="review-item">
        <div class="review-head"><strong><?= clean($r['first_name'] . ' ' . $r['last_name']) ?></strong>
          <span><?php for ($i = 1; $i <= 5; $i++): ?><i class="fa-<?= $i <= $r['rating'] ? 'solid' : 'regular' ?> fa-star"></i><?php endfor; ?></span></div>
        <p class="text-muted">on <?= clean($r['product_name']) ?></p>
        <p><?= clean($r['comment']) ?></p>
      </div>
    <?php endforeach; else: ?><p class="text-muted">No reviews yet.</p><?php endif; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
