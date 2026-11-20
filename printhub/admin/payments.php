<?php
require __DIR__ . '/_init.php';
$adminId = (int) current_admin()['id'];
if (is_post()) {
    $a = (string) ($_POST['action'] ?? '');
    if ($a === 'record') {
        $shopId = (int) ($_POST['shop_id'] ?? 0);
        $shop = shop_by_id($shopId);
        $amount = round((float) ($_POST['amount'] ?? 0), 2);
        $method = (string) ($_POST['method'] ?? '');
        $paidOn = (string) ($_POST['paid_on'] ?? '');
        $errors = [];
        if (!$shop) $errors[] = 'Choose a shop.';
        if ($amount <= 0) $errors[] = 'Enter the amount received.';
        if (!isset(payment_methods()[$method])) $errors[] = 'Choose the payment method.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidOn)) $errors[] = 'Enter the payment date.';
        $proof = null;
        if (!$errors && !empty($_FILES['proof']['name'])) {
            try {
                [$mime, $ext, $kind] = upload_validate($_FILES['proof'], 8);
                if ($kind === 'docx') throw new RuntimeException('Upload a screenshot or PDF receipt.');
                $proof = upload_store($_FILES['proof'], 'payments', $ext);
            } catch (RuntimeException $e) {
                $errors[] = 'Proof: ' . $e->getMessage();
            }
        }
        if ($errors) {
            foreach ($errors as $er) flash('error', $er);
            redirect('admin/payments.php?record=' . $shopId);
        }
        $months = max(0, min(24, (int) ($_POST['extend_months'] ?? 0)));
        subscription_record_payment($shopId, ['amount' => $amount, 'method' => $method, 'reference' => nullable(mb_substr((string) ($_POST['reference'] ?? ''), 0, 120)),
            'paid_on' => $paidOn, 'extend_months' => $months, 'proof_path' => $proof, 'status' => ($_POST['status'] ?? 'confirmed') === 'pending' ? 'pending' : 'confirmed',
            'notes' => nullable(mb_substr((string) ($_POST['notes'] ?? ''), 0, 255))], $adminId);
        flash('success', 'Payment recorded' . ($months && ($_POST['status'] ?? 'confirmed') !== 'pending' ? ' and subscription extended by ' . $months . ' month' . ($months > 1 ? 's' : '') : '') . '.');
        redirect('admin/shop.php?id=' . $shopId . '#t-pay');
    }
    if ($a === 'confirm' || $a === 'reject') {
        $p = db_row("SELECT * FROM subscription_payments WHERE id = ? AND status = 'pending'", [(int) ($_POST['id'] ?? 0)]);
        if ($p) {
            if ($a === 'confirm') {
                $months = max(0, min(24, (int) ($_POST['extend_months'] ?? $p['months_covered'])));
                db_tx(function () use ($p, $months, $adminId) {
                    $subId = $months ? subscription_add((int) $p['shop_id'], $months, null, $adminId, 'Renewed with payment #' . $p['id']) : null;
                    db_update('subscription_payments', ['status' => 'confirmed', 'subscription_id' => $subId, 'months_covered' => max(1, $months)], 'id = ?', [(int) $p['id']]);
                });
            } else {
                db_update('subscription_payments', ['status' => 'rejected'], 'id = ?', [(int) $p['id']]);
            }
            activity('admin', $adminId, 'subscription_payment_' . $a, (int) $p['shop_id'], 'subscription_payment', (int) $p['id']);
            flash('success', $a === 'confirm' ? 'Payment confirmed.' : 'Payment rejected.');
        }
        redirect('admin/payments.php?status=pending');
    }
}
$recordShop = isset($_GET['record']) ? shop_by_id((int) $_GET['record']) : null;
$status = (string) ($_GET['status'] ?? '');
$method = (string) ($_GET['method'] ?? '');
$where = ['1'];
$params = [];
if (in_array($status, ['pending', 'confirmed', 'rejected'], true)) { $where[] = 'p.status = ?'; $params[] = $status; }
if (isset(payment_methods()[$method])) { $where[] = 'p.method = ?'; $params[] = $method; }
$total = (int) db_val('SELECT COUNT(*) FROM subscription_payments p WHERE ' . implode(' AND ', $where), $params);
$pg = paginate($total, 30);
$rows = db_all('SELECT p.*, s.name shop_name, s.shop_code, a.name admin_name FROM subscription_payments p JOIN shops s ON s.id = p.shop_id LEFT JOIN admins a ON a.id = p.recorded_by
    WHERE ' . implode(' AND ', $where) . " ORDER BY p.status = 'pending' DESC, p.paid_on DESC, p.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);
$sum = (float) db_val("SELECT COALESCE(SUM(amount),0) FROM subscription_payments p WHERE p.status = 'confirmed' AND " . implode(' AND ', $where), $params);
$shops = db_all("SELECT id, name, shop_code, subscription_price FROM shops WHERE deleted_at IS NULL ORDER BY name");
admin_header('Subscription payments');
?>
<div class="row g-3">
<div class="col-xl-4 order-xl-2"><div class="p-card" id="record"><div class="p-card-head"><i class="bi bi-cash-coin"></i> Record a payment</div><div class="p-card-body">
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="record">
    <div class="mb-3"><label class="form-label">Shop</label><select class="form-select" name="shop_id" required id="recShop"><option value="">Choose…</option>
        <?php foreach ($shops as $s): $price = subscription_price_for($s); ?><option value="<?= (int) $s['id'] ?>" data-price="<?= e($price) ?>"<?= $recordShop && (int) $recordShop['id'] === (int) $s['id'] ? ' selected' : '' ?>><?= e($s['name'] . ' (' . $s['shop_code'] . ')') ?></option><?php endforeach; ?></select></div>
    <div class="row"><div class="col-6"><?= f_select('extend_months', 'Renew for', [0 => 'Do not extend', 1 => '1 month', 2 => '2 months', 3 => '3 months', 6 => '6 months', 12 => '12 months'], 1) ?></div>
        <div class="col-6"><?= f_input('amount', 'Amount (PKR)', $recordShop ? subscription_price_for($recordShop) : '', 'number', ['min' => 1, 'step' => 1, 'required' => true]) ?></div></div>
    <div class="row"><div class="col-6"><?= f_select('method', 'Method', payment_methods(), 'cash') ?></div><div class="col-6"><?= f_input('paid_on', 'Paid on', date('Y-m-d'), 'date', ['required' => true]) ?></div></div>
    <?= f_input('reference', 'Reference / transaction ID', '', 'text', ['placeholder' => 'e.g. JazzCash TID, bank slip #']) ?>
    <div class="mb-3"><label class="form-label">Payment proof (optional)</label><input class="form-control" type="file" name="proof" accept="image/png,image/jpeg,image/webp,application/pdf"></div>
    <?= f_select('status', 'Status', ['confirmed' => 'Confirmed — money received', 'pending' => 'Pending — verify later'], 'confirmed') ?>
    <?= f_input('notes', 'Notes', '', 'text') ?>
    <button class="btn btn-primary w-100">Save payment</button>
</form></div></div></div>
<div class="col-xl-8 order-xl-1">
<form class="p-filters" method="get">
    <select class="form-select" name="status"><option value="">All statuses</option><?php foreach (['pending' => 'Pending', 'confirmed' => 'Confirmed', 'rejected' => 'Rejected'] as $k => $l): ?><option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <select class="form-select" name="method"><option value="">All methods</option><?php foreach (payment_methods() as $k => $l): ?><option value="<?= $k ?>"<?= $method === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <button class="btn btn-outline-primary">Filter</button><span class="ms-auto align-self-center small text-muted">Confirmed total: <strong><?= e(money($sum)) ?></strong></span>
</form>
<div class="p-card"><div class="table-responsive"><table class="table p-table"><thead><tr><th>Shop</th><th>Amount</th><th>Method</th><th>Paid on</th><th>Months</th><th>Status</th><th>Proof</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $p): ?>
<tr><td><a href="<?= e(path('admin/shop.php?id=' . $p['shop_id'])) ?>"><?= e($p['shop_name']) ?></a><div class="small text-muted"><?= e($p['reference'] ?: '—') ?></div></td>
<td><strong><?= e(money($p['amount'])) ?></strong></td><td><?= e(payment_methods()[$p['method']]) ?></td><td class="small"><?= e(format_date($p['paid_on'])) ?></td><td><?= (int) $p['months_covered'] ?></td>
<td><span class="badge rounded-pill text-bg-<?= ['confirmed' => 'success', 'pending' => 'warning', 'rejected' => 'danger'][$p['status']] ?>"><?= e($p['status']) ?></span></td>
<td><?php if ($p['proof_path']): ?><a href="<?= e(signed_file_url('payment', (int) $p['id'], 600)) ?>" target="_blank"><i class="bi bi-paperclip"></i></a><?php endif; ?></td>
<td class="text-end text-nowrap"><?php if ($p['status'] === 'pending'): ?>
    <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="confirm"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="extend_months" value="<?= (int) $p['months_covered'] ?>"><button class="btn btn-sm btn-success" title="Confirm and extend <?= (int) $p['months_covered'] ?> month(s)"><i class="bi bi-check2"></i></button></form>
    <form method="post" class="d-inline" data-confirm="Reject this payment?"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-x"></i></button></form>
<?php endif; ?></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="8"><div class="p-empty"><i class="bi bi-cash"></i>No payments found.</div></td></tr><?php endif; ?>
</tbody></table></div></div>
<?= pagination_html($pg) ?>
</div></div>
<script>
(function () {
    var s = document.getElementById('recShop'), m = document.getElementById('f_extend_months'), a = document.getElementById('f_amount');
    function upd() { var o = s.options[s.selectedIndex], p = parseFloat(o && o.dataset.price || 0), n = parseInt(m.value, 10) || 0; if (p && n) { a.value = Math.round(p * n); } }
    s.addEventListener('change', upd); m.addEventListener('change', upd);
})();
</script>
<?php admin_footer();
