<?php
require_once __DIR__ . '/../config/config.php';
require_login('customer');
$user = current_user($conn);

$favoriteServices = db_select(
    $conn,
    'SELECT s.*, p.business_name, c.name AS city_name,
        (SELECT image_path FROM service_images si WHERE si.service_id = s.id ORDER BY is_cover DESC LIMIT 1) AS cover
     FROM favorites f
     JOIN services s ON s.id = f.favoritable_id AND f.favoritable_type = "service"
     JOIN providers p ON p.id = s.provider_id LEFT JOIN cities c ON c.id = s.city_id
     WHERE f.user_id = ? ORDER BY f.created_at DESC',
    [(int) $user['id']]
);

$favoriteProviders = db_select(
    $conn,
    'SELECT p.*, c.name AS city_name FROM favorites f
     JOIN providers p ON p.id = f.favoritable_id AND f.favoritable_type = "provider"
     LEFT JOIN cities c ON c.id = p.city_id
     WHERE f.user_id = ? ORDER BY f.created_at DESC',
    [(int) $user['id']]
);

$pageTitle = 'Favorites';
$customerActiveTab = 'favorites';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl">
    <div class="section-head">
      <span class="eyebrow"><i class="bi bi-heart"></i> Customer</span>
      <h1 class="section-heading">Favorites</h1>
    </div>

    <?php require ROOT_PATH . '/includes/customer-tabs.php'; ?>

    <?php if ($favoriteServices || $favoriteProviders): ?>
      <?php if ($favoriteServices): ?>
        <h3 style="font-size:16px;margin-bottom:14px;">Saved services</h3>
        <div class="provider-grid stagger reveal in-view" style="margin-bottom:32px;">
          <?php foreach ($favoriteServices as $svc): ?>
            <div class="service-card">
              <div class="thumb">
                <?php echo render_fav_button($conn, 'service', $svc['id']); ?>
                <a href="/pages/service.php?slug=<?php echo e($svc['slug']); ?>"><?php if ($svc['cover']): ?><img src="<?php echo e($svc['cover']); ?>"><?php endif; ?></a>
              </div>
              <a href="/pages/service.php?slug=<?php echo e($svc['slug']); ?>" class="card-body" style="display:block;">
                <div class="card-meta"><?php echo e($svc['business_name']); ?></div>
                <div class="card-title"><?php echo e($svc['title']); ?></div>
                <div class="card-footer-row"><div class="price-tag"><?php echo format_price($svc['price']); ?> <span>/ <?php echo e($svc['price_unit']); ?></span></div></div>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($favoriteProviders): ?>
        <h3 style="font-size:16px;margin-bottom:14px;">Saved providers</h3>
        <div class="provider-grid stagger reveal in-view">
          <?php foreach ($favoriteProviders as $p): ?>
            <a class="provider-card" href="/pages/provider.php?slug=<?php echo e($p['slug']); ?>">
              <div class="thumb"><?php if ($p['cover_image']): ?><img src="<?php echo e($p['cover_image']); ?>"><?php endif; ?></div>
              <div class="card-body">
                <div class="card-title"><?php echo e($p['business_name']); ?></div>
                <div class="card-meta"><i class="bi bi-geo-alt"></i> <?php echo e($p['city_name'] ?? ''); ?></div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="empty-state"><div class="icon-wrap"><i class="bi bi-heart"></i></div><h4>No favorites yet</h4><p>Tap the heart icon on any listing to save it here.</p><a href="/index.php" class="btn-w btn-primary">Start exploring</a></div>
    <?php endif; ?>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
