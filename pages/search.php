<?php
require_once __DIR__ . '/../config/config.php';

$destination = clean_input($_GET['destination'] ?? '');
$categorySlug = clean_input($_GET['category'] ?? '');
$guests = (int) ($_GET['guests'] ?? 0);

$where = ['s.status = "approved"'];
$params = [];

if ($destination !== '') {
    $where[] = '(c.name LIKE ? OR s.title LIKE ? OR s.address LIKE ?)';
    $like = '%' . $destination . '%';
    array_push($params, $like, $like, $like);
}
if ($categorySlug !== '') {
    $where[] = 'cat.slug = ?';
    $params[] = $categorySlug;
}
if ($guests > 0) {
    $where[] = '(s.max_guests IS NULL OR s.max_guests >= ?)';
    $params[] = $guests;
}

$sql = 'SELECT s.*, c.name AS city_name, cat.name AS category_name, cat.icon AS category_icon
        FROM services s
        LEFT JOIN cities c ON c.id = s.city_id
        LEFT JOIN categories cat ON cat.id = s.category_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY s.is_featured DESC, s.avg_rating DESC
        LIMIT 30';
$results = db_select($conn, $sql, $params);

$pageTitle = 'Search results';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl">
    <div class="section-head">
      <span class="eyebrow"><i class="bi bi-search"></i> Search</span>
      <h1 class="section-heading">
        <?php echo $destination !== '' ? e($destination) : 'All services'; ?>
      </h1>
      <p class="section-sub"><?php echo count($results); ?> result<?php echo count($results) === 1 ? '' : 's'; ?> found.</p>
    </div>

    <?php if ($results): ?>
      <div class="provider-grid stagger reveal in-view">
        <?php foreach ($results as $svc): ?>
          <div class="service-card">
            <div class="thumb">
              <?php if ($svc['is_featured']): ?><span class="badge-pill"><i class="bi bi-star-fill"></i> Featured</span><?php endif; ?>
              <button class="fav-btn" type="button"><i class="bi bi-heart"></i></button>
            </div>
            <div class="card-body">
              <div class="card-meta"><i class="bi <?php echo e($svc['category_icon'] ?: 'bi-tag'); ?>"></i> <?php echo e($svc['category_name']); ?></div>
              <div class="card-title"><?php echo e($svc['title']); ?></div>
              <div class="card-meta"><i class="bi bi-geo-alt"></i> <?php echo e($svc['city_name'] ?? ''); ?></div>
              <div class="card-footer-row">
                <div class="price-tag"><?php echo format_price($svc['price']); ?> <span>/ <?php echo e($svc['price_unit']); ?></span></div>
                <div class="card-rating"><i class="bi bi-star-fill"></i> <?php echo number_format((float) $svc['avg_rating'], 1); ?></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <div class="icon-wrap"><i class="bi bi-search"></i></div>
        <h4>No results yet</h4>
        <p>Either nothing matches this search, or no services have been approved yet. Full filters, maps and sorting arrive in Phase 4 of the build.</p>
        <a href="/index.php" class="btn-w btn-primary">Back to home</a>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
