<?php
require_once __DIR__ . '/../config/config.php';

$slug = clean_input($_GET['slug'] ?? '');
$service = $slug ? db_select_one(
    $conn,
    'SELECT s.*, p.business_name, p.slug AS provider_slug, p.is_verified, p.show_phone, p.show_email, p.show_map,
        u.phone AS provider_phone, u.email AS provider_email,
        c.name AS city_name, cat.name AS category_name, cat.icon AS category_icon
     FROM services s
     JOIN providers p ON p.id = s.provider_id JOIN users u ON u.id = p.user_id
     LEFT JOIN cities c ON c.id = s.city_id LEFT JOIN categories cat ON cat.id = s.category_id
     WHERE s.slug = ? AND s.status = "approved"',
    [$slug]
) : null;

if (!$service) {
    http_response_code(404);
    require ROOT_PATH . '/404.php';
    exit;
}

if (isset($_GET['intent']) && $_GET['intent'] === 'book') {
    if (!is_logged_in()) {
        redirect('/customer/login.php?redirect=' . urlencode('/pages/service.php?slug=' . $slug . '&intent=book'));
    }
    flash_set('info', 'Instant booking launches in Phase 5 of the build — the provider\'s availability and your reservation will appear right here once it ships.');
    redirect('/pages/service.php?slug=' . $slug);
}

db_execute($conn, 'UPDATE services SET view_count = view_count + 1 WHERE id = ?', [(int) $service['id']]);

$images = db_select($conn, 'SELECT * FROM service_images WHERE service_id = ? ORDER BY is_cover DESC, sort_order', [(int) $service['id']]);
$amenities = db_select($conn, 'SELECT a.* FROM service_amenity_map sam JOIN amenities a ON a.id = sam.amenity_id WHERE sam.service_id = ?', [(int) $service['id']]);
$fieldValues = db_select($conn, 'SELECT cf.field_label, cf.field_type, sfv.field_value FROM service_field_values sfv JOIN category_fields cf ON cf.id = sfv.category_field_id WHERE sfv.service_id = ? ORDER BY cf.sort_order', [(int) $service['id']]);
$reviews = db_select($conn, 'SELECT r.*, u.name AS customer_name FROM reviews r JOIN users u ON u.id = r.customer_id WHERE r.service_id = ? AND r.status = "approved" ORDER BY r.created_at DESC LIMIT 10', [(int) $service['id']]);
$similar = db_select($conn, 'SELECT * FROM services WHERE category_id = ? AND id != ? AND status = "approved" ORDER BY avg_rating DESC LIMIT 4', [(int) $service['category_id'], (int) $service['id']]);

$pageTitle = $service['title'];
$metaDescription = $service['short_description'] ?: mb_substr(strip_tags((string) $service['description']), 0, 160);
$extraCss = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">';
$extraJs = '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl">
    <div style="font-size:13.5px;color:var(--ink-mute);margin-bottom:14px;">
      <a href="/pages/category.php?slug=<?php echo e($_GET['cat'] ?? ''); ?>" style="color:var(--ink-mute);"><i class="bi <?php echo e($service['category_icon'] ?: 'bi-tag'); ?>"></i> <?php echo e($service['category_name']); ?></a>
      <?php if ($service['city_name']): ?> · <i class="bi bi-geo-alt"></i> <?php echo e($service['city_name']); ?><?php endif; ?>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
      <h1 style="font-size:clamp(24px,3vw,34px);font-weight:800;margin:0 0 18px;"><?php echo e($service['title']); ?></h1>
      <div style="position:relative;flex-shrink:0;"><?php echo render_fav_button($conn, 'service', $service['id']); ?></div>
    </div>

    <?php if ($images): ?>
      <div style="display:grid;grid-template-columns:2fr 1fr 1fr;grid-template-rows:1fr 1fr;gap:8px;border-radius:var(--radius-lg);overflow:hidden;margin-bottom:32px;max-height:420px;">
        <img src="<?php echo e($images[0]['image_path']); ?>" style="grid-row:1/3;width:100%;height:100%;object-fit:cover;">
        <?php for ($i = 1; $i < min(5, count($images)); $i++): ?>
          <img src="<?php echo e($images[$i]['image_path']); ?>" style="width:100%;height:100%;object-fit:cover;">
        <?php endfor; ?>
      </div>
    <?php else: ?>
      <div style="aspect-ratio:16/6;background:linear-gradient(160deg,var(--purple-soft),var(--purple));border-radius:var(--radius-lg);margin-bottom:32px;"></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:40px;align-items:start;">
      <div>
        <div class="panel" style="display:flex;align-items:center;justify-content:space-between;">
          <a href="/pages/provider.php?slug=<?php echo e($service['provider_slug']); ?>" style="display:flex;align-items:center;gap:12px;">
            <span class="avatar-dot" style="width:44px;height:44px;font-size:16px;"><?php echo e(strtoupper(substr($service['business_name'], 0, 1))); ?></span>
            <div>
              <div style="font-weight:700;"><?php echo e($service['business_name']); ?><?php if ($service['is_verified']): ?> <i class="bi bi-patch-check-fill" style="color:var(--purple);"></i><?php endif; ?></div>
              <div style="font-size:13px;color:var(--ink-mute);">View provider profile</div>
            </div>
          </a>
          <div class="card-rating"><i class="bi bi-star-fill"></i> <?php echo number_format((float) $service['avg_rating'], 1); ?> <span style="color:var(--ink-mute);font-weight:500;">(<?php echo (int) $service['review_count']; ?> reviews)</span></div>
        </div>

        <div class="panel">
          <h3 style="font-size:16px;margin-bottom:10px;">About this listing</h3>
          <p style="color:var(--ink-soft);line-height:1.7;white-space:pre-line;"><?php echo e($service['description']); ?></p>
        </div>

        <?php if ($fieldValues): ?>
        <div class="panel">
          <h3 style="font-size:16px;margin-bottom:14px;">Details</h3>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px 24px;">
            <?php foreach ($fieldValues as $fv): ?>
              <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--border);padding-bottom:8px;">
                <span style="color:var(--ink-mute);font-size:13.5px;"><?php echo e($fv['field_label']); ?></span>
                <strong style="font-size:13.5px;"><?php echo $fv['field_type'] === 'checkbox' ? ($fv['field_value'] === '1' ? 'Yes' : 'No') : e($fv['field_value']); ?></strong>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($amenities): ?>
        <div class="panel">
          <h3 style="font-size:16px;margin-bottom:14px;">Amenities</h3>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
            <?php foreach ($amenities as $a): ?>
              <div style="display:flex;align-items:center;gap:8px;font-size:14px;"><i class="bi <?php echo e($a['icon'] ?: 'bi-check'); ?>" style="color:var(--purple-600);"></i> <?php echo e($a['name']); ?></div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($service['show_map']): ?>
        <div class="panel">
          <h3 style="font-size:16px;margin-bottom:14px;">Location</h3>
          <?php echo render_leaflet_map($service['latitude'], $service['longitude'], e($service['title'])); ?>
          <?php if ($service['address']): ?><p style="margin-top:10px;color:var(--ink-mute);font-size:14px;"><i class="bi bi-geo-alt"></i> <?php echo e($service['address']); ?></p><?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($service['cancellation_policy']): ?>
        <div class="panel">
          <h3 style="font-size:16px;margin-bottom:10px;">Cancellation policy</h3>
          <p style="color:var(--ink-soft);font-size:14px;line-height:1.7;"><?php echo e($service['cancellation_policy']); ?></p>
        </div>
        <?php endif; ?>

        <div class="panel">
          <h3 style="font-size:16px;margin-bottom:14px;">Reviews (<?php echo (int) $service['review_count']; ?>)</h3>
          <?php if ($reviews): ?>
            <?php foreach ($reviews as $r): ?>
              <div style="border-bottom:1px solid var(--border);padding:14px 0;">
                <div style="display:flex;justify-content:space-between;">
                  <strong style="font-size:14px;"><?php echo e($r['customer_name']); ?></strong>
                  <span class="card-rating"><i class="bi bi-star-fill"></i> <?php echo (int) $r['rating']; ?></span>
                </div>
                <?php if ($r['title']): ?><div style="font-weight:600;font-size:14px;margin-top:4px;"><?php echo e($r['title']); ?></div><?php endif; ?>
                <p style="color:var(--ink-mute);font-size:13.5px;margin-top:4px;"><?php echo e($r['review_text']); ?></p>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="empty-state" style="padding:24px;"><div class="icon-wrap"><i class="bi bi-star"></i></div><h4>No reviews yet</h4><p>Reviews open up once the booking &amp; review modules ship.</p></div>
          <?php endif; ?>
        </div>
      </div>

      <div style="position:sticky;top:96px;">
        <div class="panel">
          <div class="price-tag" style="font-size:24px;"><?php echo format_price($service['price']); ?> <span style="font-size:14px;">/ <?php echo e($service['price_unit']); ?></span></div>
          <a href="?slug=<?php echo e($slug); ?>&intent=book" class="btn-w btn-primary btn-block" style="margin-top:16px;"><i class="bi bi-calendar-check"></i> Check availability</a>
          <p class="form-hint" style="text-align:center;margin-top:10px;">You won't be charged yet.</p>
          <?php if ($service['show_phone'] && $service['provider_phone']): ?>
            <a href="tel:<?php echo e($service['provider_phone']); ?>" class="btn-w btn-outline btn-block" style="margin-top:10px;"><i class="bi bi-telephone"></i> Call provider</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if ($similar): ?>
    <div style="margin-top:48px;">
      <h3 style="font-size:19px;font-weight:800;margin-bottom:20px;">Similar listings</h3>
      <div class="provider-grid">
        <?php foreach ($similar as $sim): ?>
          <div class="service-card">
            <a href="/pages/service.php?slug=<?php echo e($sim['slug']); ?>">
              <div class="thumb"></div>
              <div class="card-body">
                <div class="card-title"><?php echo e($sim['title']); ?></div>
                <div class="price-tag"><?php echo format_price($sim['price']); ?> <span>/ <?php echo e($sim['price_unit']); ?></span></div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
