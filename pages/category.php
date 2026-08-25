<?php
require_once __DIR__ . '/../config/config.php';

$slug = clean_input($_GET['slug'] ?? '');
$category = $slug ? db_select_one($conn, 'SELECT * FROM categories WHERE slug = ? AND is_active = 1', [$slug]) : null;

if (!$category) {
    http_response_code(404);
    require ROOT_PATH . '/404.php';
    exit;
}

$services = db_select(
    $conn,
    'SELECT s.*, c.name AS city_name,
        (SELECT image_path FROM service_images si WHERE si.service_id = s.id ORDER BY is_cover DESC LIMIT 1) AS cover
     FROM services s LEFT JOIN cities c ON c.id = s.city_id
     WHERE s.category_id = ? AND s.status = "approved" ORDER BY s.is_featured DESC, s.avg_rating DESC LIMIT 24',
    [(int) $category['id']]
);

$pageTitle = $category['name'];
$metaDescription = $category['seo_description'] ?: $category['description'];
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl">
    <div class="section-head">
      <span class="eyebrow"><i class="bi <?php echo e($category['icon'] ?: 'bi-grid'); ?>"></i> Category</span>
      <h1 class="section-heading"><?php echo e($category['name']); ?></h1>
      <p class="section-sub"><?php echo e($category['description']); ?></p>
    </div>

    <?php if ($services): ?>
      <div class="provider-grid stagger reveal in-view">
        <?php foreach ($services as $svc): ?>
          <div class="service-card">
            <div class="thumb">
              <?php if ($svc['is_featured']): ?><span class="badge-pill"><i class="bi bi-star-fill"></i> Featured</span><?php endif; ?>
              <?php echo render_fav_button($conn, 'service', $svc['id']); ?>
              <a href="<?php echo url('/pages/service.php'); ?>?slug=<?php echo e($svc['slug']); ?>"><?php if ($svc['cover']): ?><img src="<?php echo e($svc['cover']); ?>" alt="<?php echo e($svc['title']); ?>"><?php endif; ?></a>
            </div>
            <a href="<?php echo url('/pages/service.php'); ?>?slug=<?php echo e($svc['slug']); ?>" class="card-body" style="display:block;">
              <div class="card-meta"><i class="bi bi-geo-alt"></i> <?php echo e($svc['city_name'] ?? ''); ?></div>
              <div class="card-title"><?php echo e($svc['title']); ?></div>
              <div class="card-footer-row">
                <div class="price-tag"><?php echo format_price($svc['price']); ?> <?php if ($svc['price_unit'] !== 'fixed'): ?><span>/ <?php echo e($svc['price_unit']); ?></span><?php endif; ?></div>
                <div class="card-rating"><i class="bi bi-star-fill"></i> <?php echo number_format((float) $svc['avg_rating'], 1); ?></div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <div class="icon-wrap"><i class="bi bi-search"></i></div>
        <h4>No <?php echo e(strtolower($category['name'])); ?> listed yet</h4>
        <p>Approved provider services will appear here automatically.</p>
        <a href="<?php echo url('/provider/register.php'); ?>" class="btn-w btn-primary">Become a provider</a>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
