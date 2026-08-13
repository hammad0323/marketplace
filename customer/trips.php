<?php
require_once __DIR__ . '/../config/config.php';
require_login('customer');
$user = current_user($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verify_csrf();
    $tripId = (int) ($_POST['trip_id'] ?? 0);
    db_execute($conn, 'DELETE FROM trips WHERE id = ? AND user_id = ?', [$tripId, (int) $user['id']]);
    flash_set('success', 'Trip deleted.');
    redirect('/customer/trips.php');
}

$trips = db_select(
    $conn,
    'SELECT t.*, c.name AS city_name, tb.estimated_total
     FROM trips t LEFT JOIN cities c ON c.id = t.destination_city_id LEFT JOIN trip_budget tb ON tb.trip_id = t.id
     WHERE t.user_id = ? ORDER BY t.created_at DESC',
    [(int) $user['id']]
);

$pageTitle = 'My Trips';
$customerActiveTab = 'trips';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl">
    <div class="section-head" style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:16px;">
      <div>
        <span class="eyebrow"><i class="bi bi-map"></i> Customer</span>
        <h1 class="section-heading">My trips</h1>
      </div>
      <a href="<?php echo url('/pages/trip-planner.php'); ?>" class="btn-w btn-primary"><i class="bi bi-plus-lg"></i> Plan a new trip</a>
    </div>

    <?php require ROOT_PATH . '/includes/customer-tabs.php'; ?>

    <?php if ($trips): ?>
      <div class="provider-grid stagger reveal in-view">
        <?php foreach ($trips as $t): ?>
          <a class="service-card" href="<?php echo url('/customer/trip-builder.php'); ?>?id=<?php echo (int) $t['id']; ?>" style="display:block;">
            <div class="thumb" style="background:var(--gradient-purple);display:flex;align-items:center;justify-content:center;">
              <i class="bi bi-map" style="font-size:32px;color:rgba(255,255,255,0.6);"></i>
              <span class="badge-pill"><?php echo ucfirst($t['status']); ?></span>
            </div>
            <div class="card-body">
              <div class="card-title"><?php echo e($t['trip_name']); ?></div>
              <div class="card-meta"><i class="bi bi-geo-alt"></i> <?php echo e($t['city_name'] ?? '—'); ?></div>
              <?php if ($t['date_from']): ?><div class="card-meta"><i class="bi bi-calendar3"></i> <?php echo e(format_date($t['date_from'])); ?><?php echo $t['date_to'] ? ' – ' . e(format_date($t['date_to'])) : ''; ?></div><?php endif; ?>
              <div class="card-footer-row">
                <div class="price-tag">~<?php echo format_price($t['estimated_total'] ?? 0); ?></div>
                <div class="card-meta"><?php echo (int) $t['adults'] + (int) $t['children']; ?> travelers</div>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state"><div class="icon-wrap"><i class="bi bi-map"></i></div><h4>No trips yet</h4><p>Build your first day-by-day itinerary with a live budget estimate.</p><a href="<?php echo url('/pages/trip-planner.php'); ?>" class="btn-w btn-primary">Plan a trip</a></div>
    <?php endif; ?>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
