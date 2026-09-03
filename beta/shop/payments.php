<?php
require __DIR__ . '/../config/config.php';
require_permission('manage_payments');
$shopId = active_shop_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $commissionId = (int)$_POST['commission_id'];
    $commission = db_fetch_one("SELECT * FROM commissions WHERE id=? AND shop_id=?", 'ii', [$commissionId, $shopId]);
    if (!$commission) { flash('error', 'Invalid commission record.'); redirect(shop_url('payments.php')); }

    $receiptUpload = handle_image_upload('receipt_image', 'shops');
    if (isset($receiptUpload['error'])) { flash('error', $receiptUpload['error']); redirect(shop_url('payments.php')); }

    db_insert("INSERT INTO commission_payments (commission_id, shop_id, amount, transaction_id, payment_date, receipt_image, notes, status)
               VALUES (?,?,?,?,?,?,?, 'submitted')",
        'iidssss', [$commissionId, $shopId, (float)$_POST['amount'], trim($_POST['transaction_id']), $_POST['payment_date'], $receiptUpload['path'] ?? null, trim($_POST['notes'] ?? '')]);

    notify('admin', 1, 'Payment Submitted', "A commission payment has been submitted for review.", 'admin/payments/index.php');
    flash('success', 'Payment submitted for admin approval.');
    redirect(shop_url('payments.php'));
}

$outstanding = db_fetch_all("SELECT * FROM commissions WHERE shop_id=? AND payment_status IN ('pending','overdue') ORDER BY due_date", 'i', [$shopId]);
$history = db_fetch_all("SELECT cp.*, cm.invoice_number FROM commission_payments cp JOIN commissions cm ON cm.id=cp.commission_id WHERE cp.shop_id=? ORDER BY cp.created_at DESC", 'i', [$shopId]);
$paymentMethods = db_fetch_all("SELECT * FROM payment_methods WHERE method_key IN ('bank_transfer') AND is_enabled=1");

$dashRole = 'shop'; $pageTitle = 'Payments';
$dashUserName = current_shop_owner()['name'] ?? current_shop_staff()['name'];
$dashLogoutUrl = shop_url('logout.php');
require __DIR__ . '/../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Commission Payments</h1></div>

<?php foreach ($paymentMethods as $pm): ?>
  <div class="alert alert-info"><strong><?= clean($pm['title']) ?>:</strong> <?= nl2br(clean($pm['instructions'])) ?></div>
<?php endforeach; ?>

<div class="dash-table-card">
  <h3>Outstanding Commission</h3>
  <table class="dash-table">
    <thead><tr><th>Invoice</th><th>Amount</th><th>Due Date</th><th>Status</th><th>Submit Payment</th></tr></thead>
    <tbody>
    <?php if ($outstanding): foreach ($outstanding as $c): ?>
      <tr>
        <td><?= clean($c['invoice_number']) ?></td>
        <td><?= format_price($c['commission_amount']) ?></td>
        <td><?= date('M d, Y', strtotime($c['due_date'])) ?></td>
        <td><span class="badge badge-<?= $c['payment_status']==='overdue'?'danger':'warn' ?>"><?= clean($c['payment_status']) ?></span></td>
        <td><button type="button" class="btn btn-sm btn-primary" onclick="document.getElementById('pay-modal-<?= $c['id'] ?>').style.display='flex'">Submit Proof</button></td>
      </tr>
      <div class="modal" id="pay-modal-<?= $c['id'] ?>">
        <div class="modal-box">
          <h3>Submit Payment — <?= clean($c['invoice_number']) ?></h3>
          <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?><input type="hidden" name="commission_id" value="<?= $c['id'] ?>">
            <label>Amount</label><input type="number" step="0.01" name="amount" value="<?= $c['commission_amount'] ?>" required>
            <label>Transaction ID</label><input type="text" name="transaction_id" required>
            <label>Payment Date</label><input type="date" name="payment_date" required>
            <label>Receipt / Screenshot</label><input type="file" name="receipt_image" accept=".jpg,.jpeg,.png,.webp">
            <label>Notes</label><textarea name="notes" rows="2"></textarea>
            <button type="submit" class="btn btn-primary">Submit</button>
            <button type="button" class="btn btn-outline" onclick="document.getElementById('pay-modal-<?= $c['id'] ?>').style.display='none'">Cancel</button>
          </form>
        </div>
      </div>
    <?php endforeach; else: ?><tr><td colspan="5" class="text-muted">No outstanding commission. You're all caught up!</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div class="dash-table-card">
  <h3>Payment History</h3>
  <table class="dash-table">
    <thead><tr><th>Invoice</th><th>Amount</th><th>Transaction ID</th><th>Date</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($history as $h): ?>
        <tr><td><?= clean($h['invoice_number']) ?></td><td><?= format_price($h['amount']) ?></td><td><?= clean($h['transaction_id']) ?></td>
            <td><?= clean($h['payment_date']) ?></td><td><span class="badge badge-<?= $h['status']==='approved'?'success':($h['status']==='rejected'?'danger':'warn') ?>"><?= clean($h['status']) ?></span></td></tr>
      <?php endforeach; ?>
      <?php if (!$history): ?><tr><td colspan="5" class="text-muted">No payment submissions yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/dashboard-footer.php'; ?>
