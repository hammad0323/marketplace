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

    <?php if ($serviceCount === 0): ?>
      <div class="empty-state">
        <div class="icon-wrap"><i class="bi bi-signpost-2"></i></div>
        <h4>No services listed in <?php echo e($city['name']); ?> yet</h4>
        <p>Category browsing, search and filters for this city land in Phase 3–4 of the build. Providers can already register — approved listings will appear here automatically.</p>
        <a href="/provider/register.php" class="btn-w btn-primary">List a service in <?php echo e($city['name']); ?></a>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
