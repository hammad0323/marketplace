<?php
require __DIR__ . '/../../config/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int)$_POST['id']; $action = $_POST['action'];
    $payment = db_fetch_one("SELECT * FROM commission_payments WHERE id=?", 'i', [$id]);
    if ($payment) {
        if ($action === 'approve') {
            db_exec("UPDATE commission_payments SET status='approved' WHERE id=?", 'i', [$id]);
            db_exec("UPDATE commissions SET payment_status='paid', payment_date=NOW() WHERE id=?", 'i', [$payment['commission_id']]);
            $shop = db_fetch_one("SELECT * FROM shops WHERE id=?", 'i', [$payment['shop_id']]);
            if ($shop && $shop['status'] === 'payment_overdue') {
                $stillOverdue = db_fetch_one("SELECT id FROM commissions WHERE shop_id=? AND payment_status='overdue'", 'i', [$shop['id']]);
                if (!$stillOverdue) db_exec("UPDATE shops SET status='active', status_reason=NULL WHERE id=?", 'i', [$shop['id']]);
            }
            notify('shop_owner', $shop['owner_id'], 'Payment Approved', 'Your commission payment has been approved.', 'shop/commissions.php');
        } elseif ($action === 'reject') {
            db_exec("UPDATE commission_payments SET status='rejected' WHERE id=?", 'i', [$id]);
            $shop = db_fetch_one("SELECT * FROM shops WHERE id=?", 'i', [$payment['shop_id']]);
            notify('shop_owner', $shop['owner_id'], 'Payment Rejected', 'Your submitted commission payment was rejected. Please resubmit.', 'shop/payments.php');
        }
        audit_log('admin', current_admin()['id'], current_admin()['name'], ucfirst($action) . ' commission payment', 'payments', $id);
    }
    flash('success', 'Payment updated.');
    redirect(admin_url('payments/index.php'));
}

$payments = db_fetch_all("SELECT cp.*, s.shop_name, cm.invoice_number FROM commission_payments cp
    JOIN shops s ON s.id=cp.shop_id JOIN commissions cm ON cm.id=cp.commission_id ORDER BY cp.created_at DESC");
$methods = db_fetch_all("SELECT * FROM payment_methods ORDER BY sort_order");

$dashRole = 'admin'; $pageTitle = 'Payments'; $dashUserName = current_admin()['name']; $dashLogoutUrl = admin_url('logout.php');
require __DIR__ . '/../../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Payments</h1>
  <a href="<?= admin_url('payments/methods.php') ?>" class="btn btn-outline">Manage Payment Methods</a>
</div>
<div class="dash-table-card">
  <h3>Commission Payment Submissions</h3>
  <table class="dash-table">
    <thead><tr><th>Shop</th><th>Invoice</th><th>Amount</th><th>Transaction ID</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if ($payments): foreach ($payments as $p): ?>
      <tr>
        <td><?= clean($p['shop_name']) ?></td>
        <td><?= clean($p['invoice_number']) ?></td>
        <td><?= format_price($p['amount']) ?></td>
        <td><?= clean($p['transaction_id']) ?></td>
        <td><?= clean($p['payment_date']) ?></td>
        <td><span class="badge badge-<?= $p['status'] === 'approved' ? 'success' : ($p['status'] === 'rejected' ? 'danger' : 'warn') ?>"><?= clean($p['status']) ?></span></td>
        <td class="action-cell">
          <?php if ($p['status'] === 'submitted'): ?>
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>">
              <button name="action" value="approve" class="btn btn-sm btn-success">Approve</button>
              <button name="action" value="reject" class="btn btn-sm btn-danger">Reject</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; else: ?><tr><td colspan="7"><div class="empty-state"><h3>No payment submissions</h3></div></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/dashboard-footer.php'; ?>
