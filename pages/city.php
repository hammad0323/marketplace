<?php
require_once __DIR__ . '/../config/config.php';

$slug = clean_input($_GET['slug'] ?? '');
$city = $slug ? db_select_one($conn, 'SELECT c.*, co.name AS country_name FROM cities c JOIN countries co ON co.id = c.country_id WHERE c.slug = ? AND c.is_active = 1', [$slug]) : null;

if (!$city) {
    http_response_code(404);
    require ROOT_PATH . '/404.php';
    exit;
}

$serviceCount = db_count($conn, 'SELECT COUNT(*) FROM services WHERE city_id = ? AND status = "approved"', [(int) $city['id']]);
$providerCount = db_count($conn, 'SELECT COUNT(*) FROM providers WHERE city_id = ? AND status = "approved"', [(int) $city['id']]);
$services = db_select(
    $conn,
    'SELECT s.*, cat.name AS category_name, cat.icon AS category_icon,
        (SELECT image_path FROM service_images si WHERE si.service_id = s.id ORDER BY is_cover DESC LIMIT 1) AS cover
     FROM services s LEFT JOIN categories cat ON cat.id = s.category_id
     WHERE s.city_id = ? AND s.status = "approved" ORDER BY s.is_featured DESC, s.avg_rating DESC LIMIT 24',
    [(int) $city['id']]
);

$pageTitle = $city['name'];
$metaDescription = $city['seo_description'] ?: $city['description'];
require ROOT_PATH . '/includes/header.php';
?>
<div class="hero" style="padding:64px 0 56px;">
  <div class="hero-blob hero-blob-1"></div>
  <div class="hero-blob hero-blob-2"></div>
  <div class="container-xl" style="position:relative;z-index:2;">
    <span class="hero-eyebrow"><i class="bi bi-geo-alt-fill"></i> <?php echo e($city['country_name']); ?></span>
    <h1 class="hero-title" style="font-size:clamp(30px,4.6vw,48px);margin-top:16px;">Explore <span class="accent"><?php echo e($city['name']); ?></span></h1>
    <p class="hero-sub" style="max-width:640px;margin:0;"><?php echo e($city['description']); ?></p>
  </div>
</div>

<div class="section-tight">
  <div class="container-xl">
    <div class="hero-stats" style="justify-content:flex-start;gap:48px;margin-top:0;">
      <div class="hero-stat" style="text-align:left;">
        <div class="num" style="color:var(--ink);background:none;-webkit-text-fill-color:initial;"><?php echo (int) $providerCount; ?></div>
        <div class="label" style="color:var(--ink-mute);">Providers</div>
      </div>
      <div class="hero-stat" style="text-align:left;">
        <div class="num" style="color:var(--ink);background:none;-webkit-text-fill-color:initial;"><?php echo (int) $serviceCount; ?></div>
        <div class="label" style="color:var(--ink-mute);">Listed services</div>
      </div>
    </div>

    <?php if ($services): ?>
      <div class="provider-grid stagger reveal in-view" style="margin-top:32px;">
        <?php foreach ($services as $svc): ?>
          <div class="service-card">
            <div class="thumb">
              <?php if ($svc['is_featured']): ?><span class="badge-pill"><i class="bi bi-star-fill"></i> Featured</span><?php endif; ?>
              <?php echo render_fav_button($conn, 'service', $svc['id']); ?>
              <a href="/pages/service.php?slug=<?php echo e($svc['slug']); ?>"><?php if ($svc['cover']): ?><img src="<?php echo e($svc['cover']); ?>" alt="<?php echo e($svc['title']); ?>"><?php endif; ?></a>
            </div>
            <a href="/pages/service.php?slug=<?php echo e($svc['slug']); ?>" class="card-body" style="display:block;">
              <div class="card-meta"><i class="bi <?php echo e($svc['category_icon'] ?: 'bi-tag'); ?>"></i> <?php echo e($svc['category_name']); ?></div>
              <div class="card-title"><?php echo e($svc['title']); ?></div>
              <div class="card-footer-row">
                <div class="price-tag"><?php echo format_price($svc['price']); ?> <span>/ <?php echo e($svc['price_unit']); ?></span></div>
                <div class="card-rating"><i class="bi bi-star-fill"></i> <?php echo number_format((float) $svc['avg_rating'], 1); ?></div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <div class="icon-wrap"><i class="bi bi-signpost-2"></i></div>
        <h4>No services listed in <?php echo e($city['name']); ?> yet</h4>
        <p>Providers can already register — approved listings will appear here automatically.</p>
        <a href="/provider/register.php" class="btn-w btn-primary">List a service in <?php echo e($city['name']); ?></a>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
