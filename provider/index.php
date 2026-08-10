<?php
require_once __DIR__ . '/../config/config.php';
require_login('provider');

$user = current_user($conn);
$provider = db_select_one($conn, 'SELECT p.*, c.name AS city_name, cat.name AS category_name FROM providers p LEFT JOIN cities c ON c.id = p.city_id LEFT JOIN categories cat ON cat.id = p.category_id WHERE p.user_id = ?', [(int) $user['id']]);

$serviceCount = $provider ? db_count($conn, 'SELECT COUNT(*) FROM services WHERE provider_id = ?', [(int) $provider['id']]) : 0;
$bookingCount = $provider ? db_count($conn, 'SELECT COUNT(*) FROM bookings WHERE provider_id = ?', [(int) $provider['id']]) : 0;

$pageTitle = 'Provider Dashboard';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl">
    <div class="section-head">
      <span class="eyebrow"><i class="bi bi-shop"></i> Provider</span>
      <h1 class="section-heading"><?php echo e($provider['business_name'] ?? $user['name']); ?></h1>
      <?php if ($provider): ?>
        <p class="section-sub">
          Status:
          <span class="status-chip <?php echo e($provider['status']); ?>"><?php echo e(ucfirst($provider['status'])); ?></span>
          <?php if ($provider['is_verified']): ?><span class="status-chip approved" style="margin-left:6px;"><i class="bi bi-patch-check-fill"></i> Verified</span><?php endif; ?>
        </p>
      <?php endif; ?>
    </div>

    <?php if (!$provider): ?>
      <div class="empty-state">
        <div class="icon-wrap"><i class="bi bi-exclamation-triangle"></i></div>
        <h4>No provider profile found</h4>
        <p>Something went wrong linking your account to a business profile. Contact support.</p>
      </div>
    <?php else: ?>
      <?php if ($provider['status'] === 'pending'): ?>
        <div class="alert-w alert-info"><i class="bi bi-hourglass-split"></i> Your business is pending admin approval. You'll be able to add services once approved.</div>
      <?php elseif ($provider['status'] === 'rejected'): ?>
        <div class="alert-w alert-danger"><i class="bi bi-x-circle-fill"></i> Your business application was rejected. Contact support for details.</div>
      <?php elseif (in_array($provider['status'], ['suspended', 'blocked'], true)): ?>
        <div class="alert-w alert-danger"><i class="bi bi-slash-circle-fill"></i> Your account is currently <?php echo e($provider['status']); ?>. Contact support.</div>
      <?php endif; ?>

      <div class="stat-grid" style="grid-template-columns:repeat(3,1fr);">
        <div class="stat-card"><div class="icon-wrap"><i class="bi bi-list-ul"></i></div><div class="value"><?php echo (int) $serviceCount; ?></div><div class="label">Services</div></div>
        <div class="stat-card"><div class="icon-wrap"><i class="bi bi-calendar-check"></i></div><div class="value"><?php echo (int) $bookingCount; ?></div><div class="label">Bookings</div></div>
        <div class="stat-card"><div class="icon-wrap"><i class="bi bi-star"></i></div><div class="value"><?php echo number_format((float) $provider['avg_rating'], 1); ?></div><div class="label">Average rating</div></div>
      </div>

      <div class="roadmap-card" style="margin-top:24px;">
        <div class="icon-wrap"><i class="bi bi-cone-striped"></i></div>
        <div>
          <strong>Service management, calendar and reservations arrive in Phases 3, 5 and 6.</strong>
          <div style="font-size:13.5px;color:var(--ink-mute);">Your profile (<?php echo e($provider['category_name'] ?? '—'); ?> · <?php echo e($provider['city_name'] ?? '—'); ?>) is live in the database and ready for those modules.</div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
