<?php
require_once __DIR__ . '/../config/config.php';

$slug = clean_input($_GET['slug'] ?? '');
$provider = $slug ? db_select_one(
    $conn,
    'SELECT p.*, u.phone, u.email AS user_email, c.name AS city_name, cat.name AS category_name
     FROM providers p JOIN users u ON u.id = p.user_id
     LEFT JOIN cities c ON c.id = p.city_id LEFT JOIN categories cat ON cat.id = p.category_id
     WHERE p.slug = ? AND p.status = "approved"',
    [$slug]
) : null;

if (!$provider) {
    http_response_code(404);
    require ROOT_PATH . '/404.php';
    exit;
}

if (isset($_GET['message']) && $_GET['message'] === '1') {
    if (!is_logged_in()) {
        redirect('/customer/login.php?redirect=' . urlencode('/pages/provider.php?slug=' . $slug . '&message=1'));
    }
    if (current_user_role() !== 'customer') {
        flash_set('danger', 'Only customer accounts can message providers.');
        redirect('/pages/provider.php?slug=' . $slug);
    }
    $convId = find_or_create_conversation($conn, (int) current_user_id(), (int) $provider['id']);
    redirect('/customer/messages.php?conversation_id=' . $convId);
}

db_execute($conn, 'UPDATE providers SET profile_views = profile_views + 1 WHERE id = ?', [(int) $provider['id']]);

$badges = db_select($conn, 'SELECT pb.* FROM provider_badge_map pbm JOIN provider_badges pb ON pb.id = pbm.badge_id WHERE pbm.provider_id = ? AND pb.is_active = 1 ORDER BY pb.priority DESC', [(int) $provider['id']]);
$services = db_select(
    $conn,
    'SELECT s.*, (SELECT image_path FROM service_images si WHERE si.service_id = s.id ORDER BY is_cover DESC LIMIT 1) AS cover
     FROM services s WHERE s.provider_id = ? AND s.status = "approved" ORDER BY s.is_featured DESC, s.created_at DESC',
    [(int) $provider['id']]
);
$reviews = $provider['show_reviews'] ? db_select(
    $conn,
    'SELECT r.*, u.name AS customer_name FROM reviews r JOIN users u ON u.id = r.customer_id
     JOIN services s ON s.id = r.service_id WHERE s.provider_id = ? AND r.status = "approved" ORDER BY r.created_at DESC LIMIT 10',
    [(int) $provider['id']]
) : [];

$pageTitle = $provider['business_name'];
$metaDescription = mb_substr(strip_tags((string) $provider['description']), 0, 160);
$extraCss = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">';
$extraJs = '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>';
require ROOT_PATH . '/includes/header.php';
?>
<div style="height:220px;background:<?php echo $provider['cover_image'] ? 'url(' . e($provider['cover_image']) . ') center/cover' : 'var(--gradient-purple)'; ?>;"></div>

<div class="section-tight">
  <div class="container-xl">
    <div style="display:flex;align-items:flex-end;gap:20px;margin-top:-64px;margin-bottom:24px;flex-wrap:wrap;">
      <div style="width:110px;height:110px;border-radius:24px;background:var(--white);border:4px solid var(--white);box-shadow:var(--shadow-md);overflow:hidden;flex-shrink:0;">
        <?php if ($provider['logo']): ?>
          <img src="<?php echo e($provider['logo']); ?>" style="width:100%;height:100%;object-fit:cover;">
        <?php else: ?>
          <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--gradient-purple);color:#fff;font-size:36px;font-weight:800;"><?php echo e(strtoupper(substr($provider['business_name'], 0, 1))); ?></div>
        <?php endif; ?>
      </div>
      <div style="flex:1;">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
          <h1 style="font-size:26px;font-weight:800;margin:0;"><?php echo e($provider['business_name']); ?></h1>
          <?php foreach ($badges as $b): ?>
            <span class="badge-pill" style="position:static;background:<?php echo e($b['color']); ?>;"><i class="bi <?php echo e($b['icon'] ?: 'bi-award'); ?>"></i> <?php echo e($b['name']); ?></span>
          <?php endforeach; ?>
        </div>
        <div class="card-meta" style="margin-top:6px;">
          <?php if ($provider['city_name']): ?><i class="bi bi-geo-alt"></i> <?php echo e($provider['city_name']); ?> · <?php endif; ?>
          <?php echo e($provider['category_name'] ?? ''); ?> ·
          <i class="bi bi-star-fill" style="color:#F59E0B;"></i> <?php echo number_format((float) $provider['avg_rating'], 1); ?> (<?php echo (int) $provider['review_count']; ?> reviews)
        </div>
      </div>
      <div style="display:flex;gap:10px;">
        <a href="?slug=<?php echo e($slug); ?>&message=1" class="btn-w btn-outline"><i class="bi bi-chat-dots"></i> Message</a>
        <?php if ($provider['show_phone'] && $provider['phone']): ?>
          <a href="tel:<?php echo e($provider['phone']); ?>" class="btn-w btn-primary"><i class="bi bi-telephone"></i> Contact</a>
        <?php endif; ?>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:40px;align-items:start;">
      <div>
        <?php if ($provider['description']): ?>
        <div class="panel">
          <h3 style="font-size:16px;margin-bottom:10px;">About</h3>
          <p style="color:var(--ink-soft);line-height:1.7;white-space:pre-line;"><?php echo e($provider['description']); ?></p>
        </div>
        <?php endif; ?>

        <div class="panel">
          <h3 style="font-size:16px;margin-bottom:14px;">Services (<?php echo count($services); ?>)</h3>
          <?php if ($services): ?>
            <div class="provider-grid">
              <?php foreach ($services as $s): ?>
                <div class="service-card">
                  <a href="<?php echo url('/pages/service.php'); ?>?slug=<?php echo e($s['slug']); ?>">
                    <div class="thumb"><?php if ($s['cover']): ?><img src="<?php echo e($s['cover']); ?>"><?php endif; ?></div>
                    <div class="card-body">
                      <div class="card-title"><?php echo e($s['title']); ?></div>
                      <div class="price-tag"><?php echo format_price($s['price']); ?> <span>/ <?php echo e($s['price_unit']); ?></span></div>
                    </div>
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="empty-state" style="padding:24px;"><div class="icon-wrap"><i class="bi bi-list-ul"></i></div><h4>No services listed yet</h4></div>
          <?php endif; ?>
        </div>

        <div class="panel">
          <h3 style="font-size:16px;margin-bottom:14px;">Reviews</h3>
          <?php if ($reviews): ?>
            <?php foreach ($reviews as $r): ?>
              <div style="border-bottom:1px solid var(--border);padding:14px 0;">
                <div style="display:flex;justify-content:space-between;"><strong style="font-size:14px;"><?php echo e($r['customer_name']); ?></strong><span class="card-rating"><i class="bi bi-star-fill"></i> <?php echo (int) $r['rating']; ?></span></div>
                <p style="color:var(--ink-mute);font-size:13.5px;margin-top:4px;"><?php echo e($r['review_text']); ?></p>
                <?php if ($r['provider_response']): ?>
                  <div style="background:var(--purple-50);border-radius:10px;padding:10px 14px;margin-top:8px;">
                    <strong style="font-size:11.5px;color:var(--purple-600);">Response from <?php echo e($provider['business_name']); ?></strong>
                    <p style="font-size:13px;margin-top:3px;"><?php echo e($r['provider_response']); ?></p>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="empty-state" style="padding:24px;"><div class="icon-wrap"><i class="bi bi-star"></i></div><h4>No reviews yet</h4></div>
          <?php endif; ?>
        </div>
      </div>

      <div style="position:sticky;top:96px;">
        <?php if ($provider['show_map'] && $provider['latitude']): ?>
        <div class="panel">
          <h3 style="font-size:15px;margin-bottom:12px;">Location</h3>
          <?php echo render_leaflet_map($provider['latitude'], $provider['longitude'], e($provider['business_name']), '220px'); ?>
        </div>
        <?php endif; ?>
        <div class="panel">
          <h3 style="font-size:15px;margin-bottom:12px;">Contact</h3>
          <?php if ($provider['show_phone'] && $provider['phone']): ?><p style="font-size:14px;"><i class="bi bi-telephone"></i> <?php echo e($provider['phone']); ?></p><?php endif; ?>
          <?php if ($provider['show_email']): ?><p style="font-size:14px;"><i class="bi bi-envelope"></i> <?php echo e($provider['user_email']); ?></p><?php endif; ?>
          <?php if ($provider['show_address'] && $provider['address']): ?><p style="font-size:14px;"><i class="bi bi-geo-alt"></i> <?php echo e($provider['address']); ?></p><?php endif; ?>
          <?php if ($provider['website']): ?><p style="font-size:14px;"><i class="bi bi-globe"></i> <a href="<?php echo e($provider['website']); ?>" target="_blank" style="color:var(--purple-600);"><?php echo e($provider['website']); ?></a></p><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
