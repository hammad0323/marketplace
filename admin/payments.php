<?php
require_once __DIR__ . '/../config/config.php';
require_login('admin');
$admin = current_user($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $paymentId = (int) ($_POST['payment_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $payment = db_select_one($conn, 'SELECT * FROM payments WHERE id = ?', [$paymentId]);
    if (!$payment) {
        flash_set('danger', 'Payment not found.');
        redirect('/admin/payments.php');
    }

    if ($action === 'confirm' && $payment['status'] === 'pending') {
        db_execute($conn, 'UPDATE payments SET status = "completed", paid_at = NOW() WHERE id = ?', [$paymentId]);
        db_execute($conn, 'INSERT INTO transactions (payment_id, type, amount) VALUES (?, "charge", ?)', [$paymentId, $payment['amount']]);

        if ($payment['plan_id']) {
            $provider = db_select_one($conn, 'SELECT id, business_name FROM providers WHERE user_id = ?', [(int) $payment['user_id']]);
            if ($provider) {
                activate_membership($conn, (int) $provider['id'], (int) $payment['plan_id'], $paymentId);
                $plan = db_select_one($conn, 'SELECT name FROM membership_plans WHERE id = ?', [(int) $payment['plan_id']]);
                db_execute(
                    $conn,
                    'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, "membership_activated", "Membership activated", ?, "/provider/membership.php")',
                    [(int) $payment['user_id'], 'Your ' . ($plan['name'] ?? '') . ' plan is now active.']
                );
            }
        }
        flash_set('success', 'Payment confirmed.');
    } elseif ($action === 'reject' && $payment['status'] === 'pending') {
        db_execute($conn, 'UPDATE payments SET status = "failed" WHERE id = ?', [$paymentId]);
        flash_set('success', 'Payment marked as failed.');
    }
    log_audit($conn, (int) $admin['id'], 'payment', $paymentId, $action);
    redirect('/admin/payments.php');
}

$statusFilter = clean_input($_GET['status'] ?? '');
$where = ['1=1'];
$params = [];
if ($statusFilter !== '') {
    $where[] = 'p.status = ?';
    $params[] = $statusFilter;
}

$payments = db_select(
    $conn,
    'SELECT p.*, u.name AS user_name, u.email, mp.name AS plan_name, b.booking_ref
     FROM payments p JOIN users u ON u.id = p.user_id
     LEFT JOIN membership_plans mp ON mp.id = p.plan_id LEFT JOIN bookings b ON b.id = p.booking_id
     WHERE ' . implode(' AND ', $where) . ' ORDER BY p.created_at DESC LIMIT 50',
    $params
);

$totals = db_select_one($conn, 'SELECT COALESCE(SUM(CASE WHEN status="completed" THEN amount ELSE 0 END),0) AS completed, COALESCE(SUM(CASE WHEN status="pending" THEN amount ELSE 0 END),0) AS pending FROM payments');

$adminPageTitle = 'Payments';
$adminActive = 'payments';
require __DIR__ . '/_layout_top.php';
?>

<div class="stat-grid" style="grid-template-columns:repeat(2,1fr);">
  <div class="stat-card"><div class="icon-wrap"><i class="bi bi-cash-stack"></i></div><div class="value"><?php echo format_price($totals['completed']); ?></div><div class="label">Completed payments</div></div>
  <div class="stat-card"><div class="icon-wrap"><i class="bi bi-hourglass-split"></i></div><div class="value"><?php echo format_price($totals['pending']); ?></div><div class="label">Awaiting confirmation</div></div>
</div>

<div class="panel">
  <div class="panel-head">
    <h3>Payments</h3>
    <select onchange="window.location='?status='+this.value" style="padding:9px 14px;border-radius:999px;border:1.5px solid var(--border);font-size:13.5px;">
      <option value="">All statuses</option>
      <?php foreach (['pending', 'completed', 'failed', 'refunded'] as $s): ?>
        <option value="<?php echo $s; ?>" <?php echo $statusFilter === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <?php if ($payments): ?>
    <table class="table-w">
      <thead><tr><th>User</th><th>For</th><th>Amount</th><th>Status</th><th>Date</th><th style="text-align:right;">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($payments as $p): ?>
          <tr>
            <td><?php echo e($p['user_name']); ?><br><span style="color:var(--ink-mute);font-size:12px;"><?php echo e($p['email']); ?></span></td>
            <td><?php echo $p['plan_name'] ? e($p['plan_name']) . ' plan' : ($p['booking_ref'] ? 'Booking ' . e($p['booking_ref']) : '—'); ?></td>
            <td><?php echo format_price($p['amount']); ?></td>
            <td><?php echo status_badge($p['status'] === 'completed' ? 'approved' : ($p['status'] === 'failed' ? 'rejected' : $p['status'])); ?></td>
            <td><?php echo e(format_date($p['created_at'])); ?></td>
            <td style="text-align:right;">
              <?php if ($p['status'] === 'pending'): ?>
                <form method="post" style="display:inline;"><?php echo csrf_field(); ?><input type="hidden" name="payment_id" value="<?php echo (int) $p['id']; ?>"><input type="hidden" name="action" value="confirm"><button class="btn-w btn-primary btn-sm">Confirm</button></form>
                <form method="post" style="display:inline;"><?php echo csrf_field(); ?><input type="hidden" name="payment_id" value="<?php echo (int) $p['id']; ?>"><input type="hidden" name="action" value="reject"><button class="btn-w btn-outline btn-sm">Reject</button></form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="empty-state" style="padding:32px;"><div class="icon-wrap"><i class="bi bi-credit-card"></i></div><h4>No payments yet</h4></div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
