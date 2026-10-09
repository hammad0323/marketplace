<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('payments.manage');
if (is_post()) {
    require_csrf();
    $pay = db_one('SELECT * FROM payments WHERE id = ?', [input_int('payment_id')]);
    if ($pay && $pay['method'] !== 'cod') {
        flash('info', $pay['reference'] . ': ' . payment_verify_with_provider($pay));
        audit_log('payment_verify', 'payment', (int) $pay['id']);
    }
    admin_back('transactions');
}
$status = input('status', '', 'get');
$method = input('method', '', 'get');
$where = ['1=1'];
$params = [];
if (in_array($status, ['pending', 'processing', 'paid', 'failed', 'cancelled', 'refunded', 'partially_refunded'], true)) {
    $where[] = 'p.status = ?';
    $params[] = $status;
}
if (in_array($method, ['cod', 'easypaisa', 'jazzcash', 'card'], true)) {
    $where[] = 'p.method = ?';
    $params[] = $method;
}
$w = implode(' AND ', $where);
$total = (int) db_val("SELECT COUNT(*) FROM payments p WHERE $w", $params);
$pg = paginate($total, 40, input_int('page', 1, 'get'));
$rows = db_all("SELECT p.*, o.order_number, o.customer_name, (SELECT COUNT(*) FROM payment_transactions t WHERE t.payment_id = p.id) events FROM payments p JOIN orders o ON o.id = p.order_id WHERE $w ORDER BY p.created_at DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);
$recon = db_all("SELECT method, status, COUNT(*) n, SUM(amount) amt, SUM(refunded_amount) ref FROM payments GROUP BY method, status ORDER BY method, status");
admin_header('Payment transactions', 'transactions');
?>
<div class="row g-3 mb-3"><div class="col-lg-5"><div class="card"><div class="card-header">Reconciliation summary</div><div class="table-responsive"><table class="table table-sm mb-0">
  <thead><tr><th>Method</th><th>Status</th><th class="text-end">Count</th><th class="text-end">Amount</th><th class="text-end">Refunded</th></tr></thead>
  <tbody><?php foreach ($recon as $r): ?><tr><td><?= e(payment_method_label($r['method'])) ?></td><td><?= badge($r['status']) ?></td><td class="text-end"><?= (int) $r['n'] ?></td><td class="text-end"><?= e(money($r['amt'])) ?></td><td class="text-end"><?= e(money($r['ref'])) ?></td></tr><?php endforeach; ?></tbody>
</table></div></div></div>
<div class="col-lg-7"><form class="card card-body" method="get"><div class="row g-2 align-items-end">
  <div class="col-md-4"><label class="form-label">Status</label><select class="form-select form-select-sm" name="status"><option value="">All</option><?php foreach (['pending', 'processing', 'paid', 'failed', 'cancelled', 'refunded', 'partially_refunded'] as $s): ?><option value="<?= $s ?>"<?= $status === $s ? ' selected' : '' ?>><?= e(status_label($s)) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-4"><label class="form-label">Method</label><select class="form-select form-select-sm" name="method"><option value="">All</option><?php foreach (['cod', 'easypaisa', 'jazzcash', 'card'] as $m): ?><option value="<?= $m ?>"<?= $method === $m ? ' selected' : '' ?>><?= e(payment_method_label($m)) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-4"><button class="btn btn-sm btn-primary w-100">Filter</button></div></div>
  <p class="small text-muted mt-3 mb-0">Online payments stuck in <em>pending/processing</em> can be re-checked with the provider. Unpaid online orders are released automatically by the cron job after the configured timeout.</p></form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle">
  <thead><tr><th>Reference</th><th>Order</th><th>Method</th><th>Status</th><th class="text-end">Amount</th><th>Provider ref</th><th>Created</th><th></th></tr></thead>
  <tbody><?php foreach ($rows as $p): ?>
    <tr><td><code><?= e($p['reference']) ?></code><br><small class="text-muted"><?= (int) $p['events'] ?> events</small></td><td><a href="<?= e(admin_url('order-view', ['id' => $p['order_id']])) ?>"><?= e($p['order_number']) ?></a><br><small><?= e($p['customer_name']) ?></small></td>
      <td><?= e(payment_method_label($p['method'])) ?><br><small class="text-muted"><?= e($p['provider']) ?></small></td><td><?= badge($p['status']) ?></td><td class="text-end"><?= e(money($p['amount'])) ?><?= (float) $p['refunded_amount'] ? '<br><small class="text-danger">−' . e(money($p['refunded_amount'])) . '</small>' : '' ?></td>
      <td><small><?= e($p['provider_reference']) ?></small></td><td><small><?= e(format_date($p['created_at'], true)) ?></small></td>
      <td><?php if ($p['method'] !== 'cod' && in_array($p['status'], ['pending', 'processing', 'failed'], true)): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="payment_id" value="<?= (int) $p['id'] ?>"><button class="btn btn-sm btn-outline-primary">Verify</button></form><?php endif; ?></td></tr>
  <?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">No payments.</td></tr><?php endif; ?></tbody>
</table></div><div class="card-body border-top"><?= admin_pager($pg) ?></div></div>
<?php admin_footer();
