<?php
require __DIR__ . '/../includes/init.php';
admin_require('customers');
$id = input_int('id');
$c = db_row('SELECT * FROM customers WHERE id = ?', [$id]);
if (!$c) {
    flash('error', 'Customer not found.');
    redirect('admin/customers/');
}
if (is_post()) {
    $a = input('action', '', 'post');
    if ($a === 'notes') {
        db_exec('UPDATE customers SET admin_notes = ? WHERE id = ?', [nullable(mb_substr(input('admin_notes', '', 'post'), 0, 5000)), $id]);
        flash('success', 'Notes saved.');
    } elseif ($a === 'toggle') {
        db_exec('UPDATE customers SET is_active = 1 - is_active WHERE id = ?', [$id]);
        audit_log('customer_toggled', 'customer', $id);
        flash('success', 'Account status changed.');
    } elseif ($a === 'reset') {
        $token = password_reset_create('customer', $id);
        $link = url('account/reset-password?token=' . $token);
        if (send_mail($c['email'], 'Reset your password', mail_layout('Reset your password', '<p>A password reset was requested for your account.</p><p><a href="' . e($link) . '">Choose a new password</a> (valid for 1 hour).</p>'))) {
            flash('success', 'Password reset email sent.');
        } else {
            flash('warning', 'Email is not configured. Share this one-time link with the customer securely (valid 1 hour): ' . $link);
        }
        audit_log('customer_password_reset', 'customer', $id);
    }
    redirect('admin/customers/view.php?id=' . $id);
}
$orders = db_all('SELECT * FROM orders WHERE customer_id = ? OR (customer_id IS NULL AND email = ?) ORDER BY id DESC', [$id, $c['email']]);
$addresses = db_all('SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC', [$id]);
$spent = (float) db_val('SELECT COALESCE(SUM(grand_total - amount_refunded),0) FROM orders o WHERE customer_id = ? AND ' . sql_revenue_condition('o'), [$id]);
admin_header($c['first_name'] . ' ' . $c['last_name']);
?>
<div class="row g-4"><div class="col-lg-4">
<div class="card a-card"><div class="card-body">
    <h2 class="h5"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></h2>
    <p class="small mb-2"><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a><br><?= e($c['phone']) ?></p>
    <p class="small text-muted">Joined <?= e(format_date($c['created_at'])) ?> · Last login <?= e(format_datetime($c['last_login_at']) ?: 'never') ?><br>Newsletter: <?= (int) $c['newsletter'] ? 'yes' : 'no' ?></p>
    <div class="d-flex gap-3 mb-3"><div><div class="small text-muted">Orders</div><strong class="fs-5"><?= count($orders) ?></strong></div><div><div class="small text-muted">Total spent</div><strong class="fs-5"><?= e(money($spent)) ?></strong></div></div>
    <?= a_status_toggle((bool) $c['is_active']) ?>
    <div class="d-flex gap-2 mt-3">
        <?= a_post_button(url('admin/customers/view.php?id=' . $id), ['action' => 'toggle'], (int) $c['is_active'] ? 'Disable account' : 'Enable account', 'btn btn-sm btn-outline-dark', 'Change this account status?') ?>
        <?= a_post_button(url('admin/customers/view.php?id=' . $id), ['action' => 'reset'], 'Send password reset', 'btn btn-sm btn-outline-dark', 'Send a password reset link to this customer?') ?>
    </div>
</div></div>
<div class="card a-card"><div class="card-header">Saved addresses</div><div class="card-body small">
    <?php foreach ($addresses as $a): ?><p class="mb-2"><?= (int) $a['is_default'] ? '<span class="badge text-bg-dark">Default</span> ' : '' ?><?= e($a['full_name']) ?>, <?= e($a['address_line1']) ?>, <?= e($a['city']) ?>, <?= e($a['region']) ?> · <?= e($a['phone']) ?></p><?php endforeach; ?>
    <?php if (!$addresses): ?><span class="text-muted">None saved.</span><?php endif; ?>
</div></div>
<div class="card a-card"><div class="card-header">Internal notes</div><div class="card-body">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="notes"><textarea class="form-control mb-2" name="admin_notes" rows="4"><?= e($c['admin_notes']) ?></textarea><button class="btn btn-sm btn-dark">Save notes</button></form>
</div></div>
</div><div class="col-lg-8">
<div class="card a-card"><div class="card-header">Order history</div><div class="table-responsive"><table class="table a-table mb-0"><thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Payment</th><th class="text-end">Total</th></tr></thead><tbody>
<?php foreach ($orders as $o): ?><tr><td><a href="<?= e(url('admin/orders/view.php?id=' . $o['id'])) ?>"><?= e($o['order_number']) ?></a><?= $o['customer_id'] ? '' : ' <small class="text-muted">(guest)</small>' ?></td><td class="small"><?= e(format_datetime($o['created_at'])) ?></td><td><?= status_badge($o['status']) ?></td><td><?= status_badge($o['payment_status']) ?></td><td class="text-end"><?= e(money($o['grand_total'])) ?></td></tr><?php endforeach; ?>
<?php if (!$orders): ?><tr><td colspan="5" class="text-center text-muted py-4">No orders yet.</td></tr><?php endif; ?>
</tbody></table></div></div>
</div></div>
<?php admin_footer();
