<?php
require_once __DIR__ . '/../config/config.php';
require_login('provider');
$user = current_user($conn);
$provider = db_select_one($conn, 'SELECT * FROM providers WHERE user_id = ?', [(int) $user['id']]);
if (!$provider) {
    redirect('/provider/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $newStatus = clean_input($_POST['status'] ?? '');
    $booking = db_select_one($conn, 'SELECT * FROM bookings WHERE id = ? AND provider_id = ?', [$bookingId, (int) $provider['id']]);

    if (!$booking || !in_array($newStatus, booking_allowed_transitions($booking['status'], 'provider'), true)) {
        flash_set('danger', 'That action is not available for this booking.');
        redirect('/provider/bookings.php');
    }

    db_execute($conn, 'UPDATE bookings SET status = ? WHERE id = ?', [$newStatus, $bookingId]);

    if (in_array($newStatus, ['rejected', 'cancelled'], true)) {
        // Free the dates back up.
        db_execute($conn, 'DELETE FROM service_availability WHERE service_id = ? AND date >= ? AND date < ? AND status = "reserved"', [
            (int) $booking['service_id'], $booking['date_from'], $booking['date_to'] ?: date('Y-m-d', strtotime($booking['date_from'] . ' +1 day')),
        ]);
    }

    $notifTitles = [
        'accepted' => ['Booking accepted', 'Your booking request was accepted.'],
        'rejected' => ['Booking declined', 'Your booking request was declined.'],
        'confirmed' => ['Booking confirmed', 'Your booking is confirmed.'],
        'completed' => ['Trip completed', 'Hope you had a great experience! Leave a review.'],
        'cancelled' => ['Booking cancelled', 'The provider cancelled this booking.'],
    ];
    if (isset($notifTitles[$newStatus])) {
        db_execute(
            $conn,
            'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, "booking_status", ?, ?, "/customer/bookings.php")',
            [(int) $booking['customer_id'], $notifTitles[$newStatus][0], $notifTitles[$newStatus][1] . ' (' . $booking['booking_ref'] . ')']
        );
        $customer = db_select_one($conn, 'SELECT name, email FROM users WHERE id = ?', [(int) $booking['customer_id']]);
        $serviceTitle = db_select_one($conn, 'SELECT title FROM services WHERE id = ?', [(int) $booking['service_id']])['title'] ?? '';
        if ($customer) {
            send_email($conn, $customer['email'], $customer['name'], 'booking_status_changed', ['name' => $customer['name'], 'service_title' => $serviceTitle, 'booking_ref' => $booking['booking_ref'], 'status' => $newStatus]);
        }
    }

    log_activity($conn, (int) $user['id'], 'booking_' . $newStatus, $booking['booking_ref']);
    flash_set('success', 'Booking updated.');
    redirect('/provider/bookings.php');
}

$statusFilter = clean_input($_GET['status'] ?? '');
$where = ['b.provider_id = ?'];
$params = [(int) $provider['id']];
if ($statusFilter !== '') {
    $where[] = 'b.status = ?';
    $params[] = $statusFilter;
}

$bookings = db_select(
    $conn,
    'SELECT b.*, s.title AS service_title, u.name AS customer_name, u.email AS customer_email
     FROM bookings b JOIN services s ON s.id = b.service_id JOIN users u ON u.id = b.customer_id
     WHERE ' . implode(' AND ', $where) . ' ORDER BY b.created_at DESC',
    $params
);

$pageTitle = 'Bookings';
$providerActiveTab = 'bookings';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl">
    <div class="section-head" style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:16px;">
      <div>
        <span class="eyebrow"><i class="bi bi-calendar-check"></i> Provider</span>
        <h1 class="section-heading">Bookings</h1>
      </div>
      <select onchange="window.location='?status='+this.value" style="padding:9px 14px;border-radius:999px;border:1.5px solid var(--border);font-size:13.5px;">
        <option value="">All statuses</option>
        <?php foreach (['pending', 'accepted', 'confirmed', 'completed', 'cancelled', 'rejected'] as $s): ?>
          <option value="<?php echo $s; ?>" <?php echo $statusFilter === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <?php require ROOT_PATH . '/includes/provider-tabs.php'; ?>

    <?php if ($bookings): ?>
      <div class="panel">
        <table class="table-w">
          <thead><tr><th>Ref</th><th>Service</th><th>Customer</th><th>Dates</th><th>Total</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
          <tbody>
            <?php foreach ($bookings as $b): ?>
              <tr>
                <td><code><?php echo e($b['booking_ref']); ?></code></td>
                <td><?php echo e($b['service_title']); ?></td>
                <td><?php echo e($b['customer_name']); ?><br><span style="color:var(--ink-mute);font-size:12px;"><?php echo e($b['customer_email']); ?></span></td>
                <td><?php echo e(format_date($b['date_from'])); ?><?php echo $b['date_to'] && $b['date_to'] !== $b['date_from'] ? ' – ' . e(format_date($b['date_to'])) : ''; ?></td>
                <td><?php echo format_price($b['total_amount']); ?></td>
                <td><?php echo status_badge($b['status']); ?></td>
                <td style="text-align:right;white-space:nowrap;">
                  <?php foreach (booking_allowed_transitions($b['status'], 'provider') as $next): ?>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Mark booking <?php echo $next; ?>?');">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="booking_id" value="<?php echo (int) $b['id']; ?>">
                      <input type="hidden" name="status" value="<?php echo $next; ?>">
                      <button type="submit" class="btn-w btn-sm <?php echo $next === 'rejected' || $next === 'cancelled' ? 'btn-outline' : 'btn-primary'; ?>"><?php echo ucfirst($next); ?></button>
                    </form>
                  <?php endforeach; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty-state"><div class="icon-wrap"><i class="bi bi-calendar-check"></i></div><h4>No bookings yet</h4><p>Booking requests from customers will show up here.</p></div>
    <?php endif; ?>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
