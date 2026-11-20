<?php
require __DIR__ . '/_init.php';
$id = (int) ($_GET['id'] ?? 0);
$shop = shop_by_id($id);
if (!$shop) {
    flash('error', 'Shop not found.');
    redirect('admin/shops.php');
}
$adminId = (int) current_admin()['id'];
if (is_post()) {
    $a = (string) ($_POST['action'] ?? '');
    switch ($a) {
        case 'status':
            $st = (string) ($_POST['status'] ?? '');
            if (in_array($st, ['active', 'suspended', 'deactivated'], true)) {
                db_update('shops', ['status' => $st], 'id = ?', [$id]);
                if ($st !== 'active') {
                    db_exec("UPDATE auth_tokens SET revoked_at = NOW() WHERE user_type = 'owner' AND shop_id = ? AND revoked_at IS NULL", [$id]);
                }
                notify('shop', $id, $id, null, 'account', $st === 'active' ? 'Your shop account is active' : 'Your shop account was ' . $st, $st === 'active' ? 'Online ordering is available again.' : 'Please contact ' . platform_name() . ' support.');
                activity('admin', $adminId, 'shop_' . $st, $id, 'shop', $id);
                flash('success', 'Shop ' . ($st === 'active' ? 'activated' : $st) . '.');
            }
            break;
        case 'reset_password':
            $pw = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(9))), 0, 10) . random_int(1, 9);
            db_exec("UPDATE shop_users SET password_hash = ?, must_change_password = 1 WHERE shop_id = ? AND role = 'owner'", [password_hash($pw, PASSWORD_DEFAULT), $id]);
            db_exec("UPDATE auth_tokens SET revoked_at = NOW() WHERE user_type = 'owner' AND shop_id = ? AND revoked_at IS NULL", [$id]);
            $_SESSION['new_shop_credentials'] = ['id' => $id, 'username' => (string) db_val("SELECT username FROM shop_users WHERE shop_id = ? AND role = 'owner'", [$id]), 'password' => $pw];
            activity('admin', $adminId, 'owner_password_reset', $id, 'shop', $id);
            flash('success', 'Password reset. The owner was signed out of all devices.');
            break;
        case 'add_months':
            $m = max(1, min(24, (int) ($_POST['months'] ?? 1)));
            subscription_add($id, $m, null, $adminId, mb_substr((string) ($_POST['notes'] ?? ''), 0, 255) ?: 'Added by admin');
            flash('success', 'Subscription extended by ' . $m . ' month' . ($m > 1 ? 's' : '') . '. Record the payment under Payments if one was received.');
            break;
        case 'sub_status':
            $sub = subscription_current($id);
            $st = $_POST['sub_status'] ?? '';
            if ($sub && in_array($st, ['active', 'suspended', 'cancelled'], true)) {
                db_update('subscriptions', ['status' => $st], 'id = ?', [(int) $sub['id']]);
                activity('admin', $adminId, 'subscription_' . $st, $id, 'subscription', (int) $sub['id']);
                flash('success', 'Subscription ' . ($st === 'active' ? 'reactivated' : $st) . '.');
            }
            break;
        case 'sub_edit':
            $sub = subscription_current($id);
            $end = (string) ($_POST['end_date'] ?? '');
            if ($sub && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) && strtotime($end) >= strtotime($sub['start_date'])) {
                db_update('subscriptions', ['end_date' => $end, 'grace_days' => max(0, min(60, (int) ($_POST['grace_days'] ?? 3))), 'reminder_sent_at' => null, 'expired_notified_at' => null], 'id = ?', [(int) $sub['id']]);
                activity('admin', $adminId, 'subscription_edited', $id, 'subscription', (int) $sub['id'], ['end' => $end]);
                flash('success', 'Subscription dates updated.');
            } else {
                flash('error', 'Enter a valid end date after the start date.');
            }
            break;
        case 'delete':
            require_super_admin();
            db_update('shops', ['status' => 'deactivated', 'deleted_at' => now()], 'id = ?', [$id]);
            db_exec('UPDATE shop_users SET deleted_at = NOW(), is_active = 0 WHERE shop_id = ?', [$id]);
            db_exec("UPDATE auth_tokens SET revoked_at = NOW() WHERE user_type = 'owner' AND shop_id = ? AND revoked_at IS NULL", [$id]);
            activity('admin', $adminId, 'shop_deleted', $id, 'shop', $id);
            flash('success', 'Shop deleted (soft delete — records are kept for reports).');
            redirect('admin/shops.php');
    }
    redirect('admin/shop.php?id=' . $id . (isset($_POST['tab']) ? '#' . preg_replace('/\W/', '', (string) $_POST['tab']) : ''));
}
$shop = shop_by_id($id);
$st = subscription_state($id, $shop);
$owner = db_row("SELECT * FROM shop_users WHERE shop_id = ? AND role = 'owner' AND deleted_at IS NULL", [$id]);
$subs = db_all('SELECT s.*, a.name admin_name FROM subscriptions s LEFT JOIN admins a ON a.id = s.created_by WHERE s.shop_id = ? ORDER BY s.end_date DESC, s.id DESC', [$id]);
$payments = db_all('SELECT p.*, a.name admin_name FROM subscription_payments p LEFT JOIN admins a ON a.id = p.recorded_by WHERE p.shop_id = ? ORDER BY p.paid_on DESC, p.id DESC', [$id]);
$orders = db_all('SELECT * FROM orders WHERE shop_id = ? ORDER BY id DESC LIMIT 50', [$id]);
$report = shop_report($id, 'month');
$dash = shop_dashboard($id);
$log = db_all('SELECT * FROM activity_logs WHERE shop_id = ? ORDER BY id DESC LIMIT 40', [$id]);
$cred = ($_SESSION['new_shop_credentials']['id'] ?? 0) === $id ? $_SESSION['new_shop_credentials'] : null;
unset($_SESSION['new_shop_credentials']);
admin_header($shop['name']);
?>
<?php if ($cred): ?>
<div class="alert alert-success d-flex flex-wrap align-items-center gap-3"><i class="bi bi-key fs-4"></i><div><strong>Owner login</strong> — shown once, copy it now.<br>
    Username: <code><?= e($cred['username']) ?></code> · Password: <code><?= e($cred['password']) ?></code> · Sign in at <code><?= e(url('owner/')) ?></code> or the app.</div>
    <button class="btn btn-sm btn-success ms-auto" data-copy="<?= e("Shop: {$shop['name']}\nShop ID: {$shop['shop_code']}\nUsername: {$cred['username']}\nPassword: {$cred['password']}\nLogin: " . url('owner/')) ?>"><i class="bi bi-clipboard"></i> Copy</button></div>
<?php endif; ?>
<div class="row g-3 mb-1">
<div class="col-xl-8"><div class="p-card"><div class="p-card-body d-flex flex-wrap gap-4">
    <div><?php if ($shop['logo_path']): ?><img src="<?= e(path('media/' . $shop['logo_path'])) ?>" alt="" style="width:84px;height:84px;object-fit:contain;border-radius:16px;border:1px solid #E2E8F0"><?php else: ?><span class="p-logo" style="width:84px;height:84px;font-size:2rem;border-radius:18px"><i class="bi bi-shop"></i></span><?php endif; ?></div>
    <div class="flex-grow-1">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-2"><code class="fs-6"><?= e($shop['shop_code']) ?></code><span class="badge rounded-pill text-bg-<?= ['active' => 'success', 'suspended' => 'warning', 'deactivated' => 'secondary'][$shop['status']] ?>"><?= e(ucfirst($shop['status'])) ?></span><?= sub_badge($st['state']) ?>
            <?php if (!(int) $shop['accepting_orders']): ?><span class="p-chip"><i class="bi bi-pause"></i> Owner paused orders</span><?php endif; ?></div>
        <dl class="p-kv"><dt>Owner</dt><dd><?= e($shop['owner_name']) ?> · login <code><?= e($owner['username'] ?? '—') ?></code><?= $owner && $owner['last_login_at'] ? ' <span class="text-muted small">last seen ' . e(time_ago($owner['last_login_at'])) . '</span>' : ' <span class="text-muted small">never signed in</span>' ?></dd>
            <dt>Contact</dt><dd><a href="tel:<?= e($shop['phone']) ?>"><?= e($shop['phone']) ?></a><?= $shop['whatsapp'] ? ' · WhatsApp ' . e($shop['whatsapp']) : '' ?><?= $shop['email'] ? ' · ' . e($shop['email']) : '' ?></dd>
            <dt>Address</dt><dd><?= e($shop['address']) ?><?= $shop['area'] ? ', ' . e($shop['area']) : '' ?>, <?= e($shop['city']) ?>
                <?php if ($shop['latitude']): ?> · <a target="_blank" rel="noopener" href="https://www.google.com/maps?q=<?= e($shop['latitude'] . ',' . $shop['longitude']) ?>">Map <i class="bi bi-box-arrow-up-right"></i></a><?php endif; ?></dd>
            <dt>Customer page</dt><dd><a href="<?= e(shop_url($shop)) ?>" target="_blank"><?= e(shop_url($shop)) ?></a></dd>
            <dt>Created</dt><dd><?= e(format_datetime($shop['created_at'])) ?></dd></dl>
    </div>
    <div class="d-flex flex-column gap-2"><a class="btn btn-outline-primary btn-sm" href="<?= e(path('admin/shop-form.php?id=' . $id)) ?>"><i class="bi bi-pencil"></i> Edit</a>
        <form method="post" data-confirm="Reset the owner's password? They will be signed out everywhere."><?= csrf_field() ?><input type="hidden" name="action" value="reset_password"><button class="btn btn-light btn-sm w-100"><i class="bi bi-key"></i> Reset password</button></form>
        <?php if ($shop['status'] !== 'active'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="active"><button class="btn btn-success btn-sm w-100"><i class="bi bi-play"></i> Activate</button></form><?php endif; ?>
        <?php if ($shop['status'] === 'active'): ?><form method="post" data-confirm="Suspend this shop? Online ordering stops and the owner is signed out."><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="suspended"><button class="btn btn-warning btn-sm w-100"><i class="bi bi-pause"></i> Suspend</button></form><?php endif; ?>
        <?php if ($shop['status'] !== 'deactivated'): ?><form method="post" data-confirm="Deactivate this shop? The owner can no longer sign in."><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="deactivated"><button class="btn btn-light btn-sm w-100 text-danger"><i class="bi bi-slash-circle"></i> Deactivate</button></form><?php endif; ?>
    </div>
</div></div></div>
<div class="col-xl-4"><div class="p-card"><div class="p-card-head"><i class="bi bi-calendar-check"></i> Subscription</div><div class="p-card-body">
    <dl class="p-kv" style="grid-template-columns:110px 1fr"><dt>Status</dt><dd><?= sub_badge($st['state']) ?> <span class="small text-muted"><?= e($st['label']) ?></span></dd>
        <dt>Monthly price</dt><dd><?= e(money($st['price'])) ?><?= $shop['subscription_price'] !== null ? ' <span class="p-chip">custom</span>' : ' <span class="text-muted small">default</span>' ?></dd>
        <dt>Current period</dt><dd><?= $st['subscription'] ? e(format_date($st['subscription']['start_date'])) . ' – ' . e(format_date($st['end_date'])) : '—' ?></dd>
        <?php if ($st['grace_until']): ?><dt>Grace until</dt><dd><?= e(format_date($st['grace_until'])) ?></dd><?php endif; ?></dl>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <a class="btn btn-primary btn-sm" href="<?= e(path('admin/payments.php?record=' . $id)) ?>"><i class="bi bi-cash-coin"></i> Record payment &amp; renew</a>
        <button class="btn btn-light btn-sm" data-bs-toggle="collapse" data-bs-target="#subTools"><i class="bi bi-sliders"></i> More</button></div>
    <div class="collapse mt-3" id="subTools">
        <form method="post" class="d-flex gap-2 mb-2"><?= csrf_field() ?><input type="hidden" name="action" value="add_months"><select class="form-select form-select-sm" name="months"><?php foreach ([1, 2, 3, 6, 12] as $m): ?><option value="<?= $m ?>"><?= $m ?> month<?= $m > 1 ? 's' : '' ?></option><?php endforeach; ?></select><button class="btn btn-outline-primary btn-sm text-nowrap">Extend (no payment)</button></form>
        <?php if ($st['subscription']): ?>
        <form method="post" class="row g-2 mb-2"><?= csrf_field() ?><input type="hidden" name="action" value="sub_edit">
            <div class="col-7"><input class="form-control form-control-sm" type="date" name="end_date" value="<?= e($st['end_date']) ?>" title="End date"></div>
            <div class="col-3"><input class="form-control form-control-sm" type="number" name="grace_days" min="0" max="60" value="<?= (int) $st['subscription']['grace_days'] ?>" title="Grace days"></div>
            <div class="col-2"><button class="btn btn-light btn-sm w-100" title="Save dates"><i class="bi bi-check2"></i></button></div></form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="sub_status">
            <?php if ($st['subscription']['status'] === 'active'): ?><input type="hidden" name="sub_status" value="suspended"><button class="btn btn-light btn-sm text-warning w-100"><i class="bi bi-pause"></i> Suspend subscription</button>
            <?php else: ?><input type="hidden" name="sub_status" value="active"><button class="btn btn-light btn-sm text-success w-100"><i class="bi bi-play"></i> Reactivate subscription</button><?php endif; ?></form>
        <?php endif; ?>
    </div>
</div></div></div>
</div>

<div class="row g-3 mb-1">
    <div class="col-6 col-xl-3"><?= stat_card('Orders (30 days)', (string) $report['orders'], 'receipt', 'violet', $report['completed'] . ' completed') ?></div>
    <div class="col-6 col-xl-3"><?= stat_card('Revenue (30 days)', money($report['revenue']), 'cash-stack', 'teal', 'Avg ' . money($report['average_order_value'])) ?></div>
    <div class="col-6 col-xl-3"><?= stat_card('Pending now', (string) ($dash['pending'] ?? 0), 'hourglass-split', 'amber') ?></div>
    <div class="col-6 col-xl-3"><?= stat_card('Paid to platform', money(array_sum(array_map(fn($p) => $p['status'] === 'confirmed' ? (float) $p['amount'] : 0, $payments))), 'wallet2', 'blue', count($payments) . ' payments') ?></div>
</div>

<ul class="nav nav-pills mb-3 gap-1" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#t-orders">Orders</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#t-subs">Subscription history</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#t-pay">Payments</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#t-analytics">Analytics</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#t-log">Activity</button></li>
</ul>
<div class="tab-content">
<div class="tab-pane fade show active" id="t-orders"><div class="p-card"><div class="table-responsive"><table class="table p-table"><thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Mode</th><th class="text-end">Amount</th><th>Date</th></tr></thead><tbody>
    <?php foreach ($orders as $o): ?><tr><td><strong><?= e($o['order_number']) ?></strong></td><td><?= e($o['customer_name']) ?><div class="small text-muted"><?= e($o['customer_phone']) ?></div></td><td><?= order_badge($o['status']) ?></td>
        <td class="small"><?= $o['pricing_mode'] === 'auto' ? 'Auto price' : 'Quote' ?></td><td class="text-end"><?= order_amount($o) !== null ? e(money(order_amount($o))) : '—' ?></td><td class="small text-muted"><?= e(format_datetime($o['created_at'])) ?></td></tr><?php endforeach; ?>
    <?php if (!$orders): ?><tr><td colspan="6"><div class="p-empty"><i class="bi bi-inbox"></i>No orders yet.</div></td></tr><?php endif; ?>
</tbody></table></div></div></div>
<div class="tab-pane fade" id="t-subs"><div class="p-card"><div class="table-responsive"><table class="table p-table"><thead><tr><th>Period</th><th>Months</th><th>Price / month</th><th>Grace</th><th>Status</th><th>Added by</th><th>Notes</th></tr></thead><tbody>
    <?php foreach ($subs as $s): ?><tr><td><?= e(format_date($s['start_date'])) ?> – <?= e(format_date($s['end_date'])) ?></td><td><?= (int) $s['months'] ?></td><td><?= e(money($s['price'])) ?></td><td><?= (int) $s['grace_days'] ?> days</td>
        <td><span class="badge rounded-pill text-bg-<?= ['active' => 'success', 'suspended' => 'warning', 'cancelled' => 'secondary'][$s['status']] ?>"><?= e($s['status']) ?></span></td><td class="small"><?= e($s['admin_name'] ?? 'System') ?></td><td class="small text-muted"><?= e($s['notes']) ?></td></tr><?php endforeach; ?>
    <?php if (!$subs): ?><tr><td colspan="7"><div class="p-empty"><i class="bi bi-calendar"></i>No subscription periods yet.</div></td></tr><?php endif; ?>
</tbody></table></div></div></div>
<div class="tab-pane fade" id="t-pay"><div class="p-card"><div class="table-responsive"><table class="table p-table"><thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th><th>Months</th><th>Status</th><th>Proof</th><th>By</th></tr></thead><tbody>
    <?php foreach ($payments as $p): ?><tr><td><?= e(format_date($p['paid_on'])) ?></td><td><strong><?= e(money($p['amount'])) ?></strong></td><td><?= e(payment_methods()[$p['method']]) ?></td><td class="small"><?= e($p['reference']) ?></td><td><?= (int) $p['months_covered'] ?></td>
        <td><span class="badge rounded-pill text-bg-<?= ['confirmed' => 'success', 'pending' => 'warning', 'rejected' => 'danger'][$p['status']] ?>"><?= e($p['status']) ?></span></td>
        <td><?php if ($p['proof_path']): ?><a href="<?= e(signed_file_url('payment', (int) $p['id'], 600)) ?>" target="_blank"><i class="bi bi-paperclip"></i> View</a><?php endif; ?></td><td class="small"><?= e($p['admin_name'] ?? '—') ?></td></tr><?php endforeach; ?>
    <?php if (!$payments): ?><tr><td colspan="8"><div class="p-empty"><i class="bi bi-cash"></i>No payments recorded.</div></td></tr><?php endif; ?>
</tbody></table></div></div></div>
<div class="tab-pane fade" id="t-analytics"><div class="row g-3">
    <div class="col-lg-8"><div class="p-card"><div class="p-card-head">Orders &amp; revenue — last 30 days</div><div class="p-card-body"><div class="chart-box"><canvas id="chShop"></canvas></div></div></div></div>
    <div class="col-lg-4"><div class="p-card"><div class="p-card-head">Most ordered services</div><div class="p-card-body">
        <?php foreach ($report['top_categories'] as $c): ?><div class="d-flex justify-content-between border-bottom py-2"><span><?= e($c['name']) ?></span><strong><?= (int) $c['count'] ?></strong></div><?php endforeach; ?>
        <?php if (!$report['top_categories']): ?><p class="text-muted small mb-0">No orders in this period.</p><?php endif; ?>
        <dl class="p-kv mt-3" style="grid-template-columns:140px 1fr"><dt>Completed</dt><dd><?= (int) $report['completed'] ?></dd><dt>Cancelled / rejected</dt><dd><?= (int) $report['cancelled'] ?></dd><dt>Average order</dt><dd><?= e(money($report['average_order_value'])) ?></dd></dl>
    </div></div></div></div></div>
<div class="tab-pane fade" id="t-log"><div class="p-card"><div class="p-card-body"><ul class="p-timeline">
    <?php foreach ($log as $l): ?><li><?= e(str_replace('_', ' ', $l['action'])) ?> <span class="text-muted small">by <?= e($l['actor_type']) ?></span><small><?= e(format_datetime($l['created_at'])) ?></small></li><?php endforeach; ?>
    <?php if (!$log): ?><li class="text-muted">No activity yet.</li><?php endif; ?></ul></div></div></div>
</div>
<?php if (current_admin()['role'] === 'super_admin'): ?>
<form method="post" class="mt-4 text-end" data-confirm="Delete this shop? It disappears from lists; orders and payments stay in the reports."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-link text-danger btn-sm"><i class="bi bi-trash"></i> Delete shop</button></form>
<?php endif; ?>
<script>window.PH_SHOP_REPORT = <?= json_encode($report['daily']) ?>;</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var d = window.PH_SHOP_REPORT, el = document.getElementById('chShop');
    if (!window.Chart || !el) { return; }
    new Chart(el, { data: { labels: d.map(function (x) { return x.date.slice(5); }), datasets: [
        { type: 'bar', label: 'Orders', data: d.map(function (x) { return x.orders; }), backgroundColor: '#99F6E4', borderRadius: 4, yAxisID: 'y' },
        { type: 'line', label: 'Revenue', data: d.map(function (x) { return x.revenue; }), borderColor: '#0F766E', tension: .35, pointRadius: 0, yAxisID: 'y1' }] },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, y1: { beginAtZero: true, position: 'right', grid: { display: false } }, x: { grid: { display: false } } },
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } } } });
});
</script>
<?php admin_footer(['vendor/chartjs/chart.umd.js']);
