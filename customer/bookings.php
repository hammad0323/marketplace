<?php
require_once __DIR__ . '/../config/config.php';
require_login('customer');
$user = current_user($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $booking = db_select_one($conn, 'SELECT * FROM bookings WHERE id = ? AND customer_id = ?', [$bookingId, (int) $user['id']]);
    if (!$booking || !in_array('cancelled', booking_allowed_transitions($booking['status'], 'customer'), true)) {
        flash_set('danger', 'This booking can no longer be cancelled.');
        redirect('/customer/bookings.php');
    }
    db_execute($conn, 'UPDATE bookings SET status = "cancelled" WHERE id = ?', [$bookingId]);
    db_execute($conn, 'DELETE FROM service_availability WHERE service_id = ? AND date >= ? AND date < ? AND status = "reserved"', [
        (int) $booking['service_id'], $booking['date_from'], $booking['date_to'] ?: date('Y-m-d', strtotime($booking['date_from'] . ' +1 day')),
    ]);
    $providerUser = db_select_one($conn, 'SELECT user_id FROM providers WHERE id = ?', [(int) $booking['provider_id']]);
    if ($providerUser) {
        db_execute($conn, 'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, "booking_status", "Booking cancelled", ?, "/provider/bookings.php")', [(int) $providerUser['user_id'], 'Customer cancelled ' . $booking['booking_ref']]);
    }
    flash_set('success', 'Booking cancelled.');
    redirect('/customer/bookings.php');
}

$statusFilter = clean_input($_GET['status'] ?? '');
$where = ['b.customer_id = ?'];
$params = [(int) $user['id']];
if ($statusFilter !== '') {
    $where[] = 'b.status = ?';
    $params[] = $statusFilter;
}

$bookings = db_select(
    $conn,
    'SELECT b.*, s.title AS service_title, s.slug AS service_slug, p.business_name
     FROM bookings b JOIN services s ON s.id = b.service_id JOIN providers p ON p.id = b.provider_id
     WHERE ' . implode(' AND ', $where) . ' ORDER BY b.created_at DESC',
    $params
);

$pageTitle = 'My Bookings';
$customerActiveTab = 'bookings';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl">
    <div class="section-head">
      <span class="eyebrow"><i class="bi bi-calendar-check"></i> Customer</span>
      <h1 class="section-heading">My bookings</h1>
    </div>

    <?php require ROOT_PATH . '/includes/customer-tabs.php'; ?>

    <?php if ($bookings): ?>
      <div style="display:flex;flex-direction:column;gap:14px;">
        <?php foreach ($bookings as $b): ?>
          <div class="panel" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
            <div>
              <div style="font-weight:700;"><a href="/pages/service.php?slug=<?php echo e($b['service_slug']); ?>" style="color:var(--ink);"><?php echo e($b['service_title']); ?></a></div>
              <div class="card-meta"><?php echo e($b['business_name']); ?> · <?php echo e(format_date($b['date_from'])); ?><?php echo $b['date_to'] && $b['date_to'] !== $b['date_from'] ? ' – ' . e(format_date($b['date_to'])) : ''; ?></div>
              <div style="font-size:12px;color:var(--ink-mute);margin-top:4px;">Ref: <code><?php echo e($b['booking_ref']); ?></code></div>
            </div>
            <div style="display:flex;align-items:center;gap:14px;">
              <div class="price-tag"><?php echo format_price($b['total_amount']); ?></div>
              <?php echo status_badge($b['status']); ?>
              <?php if (in_array('cancelled', booking_allowed_transitions($b['status'], 'customer'), true)): ?>
                <form method="post" onsubmit="return confirm('Cancel this booking?');">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="booking_id" value="<?php echo (int) $b['id']; ?>">
                  <button type="submit" class="btn-w btn-outline btn-sm">Cancel</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state"><div class="icon-wrap"><i class="bi bi-calendar-check"></i></div><h4>No bookings yet</h4><p>When you reserve a service, it'll show up here.</p><a href="/index.php" class="btn-w btn-primary">Start exploring</a></div>
    <?php endif; ?>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
