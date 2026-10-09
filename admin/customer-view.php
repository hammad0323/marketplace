<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('customers.view');
$id = input_int('id', 0, 'get');
$c = db_one('SELECT * FROM customers WHERE id = ?', [$id]);
if (!$c) {
    flash('error', 'Customer not found.');
    redirect(admin_url('customers'));
}
if (is_post()) {
    require_csrf();
    require_admin('customers.edit');
    switch (input('action')) {
        case 'update':
            $status = input('status') === 'disabled' ? 'disabled' : 'active';
            $phone = input('phone');
            if ($phone !== '' && !valid_phone($phone)) {
                flash('error', 'Invalid phone number.');
                break;
            }
            db_exec('UPDATE customers SET first_name = ?, last_name = ?, phone = ?, status = ?, admin_notes = ? WHERE id = ?',
                [mb_substr(input('first_name'), 0, 80) ?: $c['first_name'], mb_substr(input('last_name'), 0, 80), $phone ?: null, $status, mb_substr(input('admin_notes'), 0, 5000) ?: null, $id]);
            if ($status === 'disabled' && $c['status'] !== 'disabled') {
                db_exec('DELETE FROM carts WHERE customer_id = ?', [$id]);
            }
            audit_log('customer_updated', 'customer', $id, ['status' => $status]);
            flash('success', 'Customer updated.');
            break;
        case 'reset':
            $token = create_password_reset('customer', $id);
            send_template_email($c['email'], 'Reset your ' . setting('site_name', 'Beglet') . ' password', 'password_reset', ['link' => url('account/reset-password', ['token' => $token])]);
            audit_log('customer_password_reset_sent', 'customer', $id);
            flash('success', 'A password reset link was emailed to ' . $c['email'] . '.');
            break;
    }
    redirect(admin_url('customer-view', ['id' => $id]));
}
$orders = db_all('SELECT * FROM orders WHERE customer_id = ? OR (customer_id IS NULL AND email = ?) ORDER BY created_at DESC', [$id, $c['email']]);
$spent = array_sum(array_map(fn($o) => in_array($o['status'], ['cancelled', 'refunded'], true) ? 0 : (float) $o['grand_total'] - (float) $o['refunded_total'], $orders));
$addresses = db_all('SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC', [$id]);
$sub = db_one('SELECT * FROM newsletter_subscribers WHERE email = ?', [$c['email']]);
admin_header(trim($c['first_name'] . ' ' . $c['last_name']), 'customers');
?>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card mb-3"><div class="card-body">
      <div class="row text-center mb-3"><div class="col"><div class="stat-card__label">Orders</div><div class="stat-card__value"><?= count($orders) ?></div></div><div class="col"><div class="stat-card__label">Spent</div><div class="stat-card__value fs-5"><?= e(money($spent)) ?></div></div></div>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update">
        <fieldset <?= can('customers.edit') ? '' : 'disabled' ?>>
        <?= f_text('first_name', 'First name', $c['first_name']) ?><?= f_text('last_name', 'Last name', $c['last_name']) ?>
        <?= f_text('email_ro', 'Email', $c['email'], ['disabled' => true]) ?><?= f_text('phone', 'Phone', $c['phone']) ?>
        <?= f_select('status', 'Account status', ['active' => 'Active', 'disabled' => 'Disabled'], $c['status']) ?>
        <?= f_textarea('admin_notes', 'Internal notes', $c['admin_notes'], ['rows' => 3]) ?>
        <?= f_submit() ?></fieldset>
      </form>
      <?php if (can('customers.edit')): ?><form method="post" class="mt-2" data-confirm="Email a password reset link to this customer?"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><button class="btn btn-sm btn-light w-100"><i class="bi bi-key"></i> Send password reset link</button></form><?php endif; ?>
      <p class="small text-muted mt-3 mb-0">Joined <?= e(format_date($c['created_at'])) ?> · last sign-in <?= e(format_date($c['last_login_at'], true)) ?: 'never' ?><br>Newsletter: <?= $sub ? e(status_label($sub['status'])) : 'not subscribed' ?></p>
    </div></div>
    <div class="card"><div class="card-header">Saved addresses</div><div class="card-body">
      <?php foreach ($addresses as $a): ?><p class="small"><strong><?= e($a['label']) ?></strong><?= $a['is_default'] ? ' (default)' : '' ?><br><?= e($a['full_name']) ?>, <?= e($a['address_line1']) ?>, <?= e($a['city']) ?>, <?= e($a['region']) ?> · <?= e($a['phone']) ?></p><?php endforeach; ?>
      <?php if (!$addresses): ?><p class="text-muted small mb-0">None saved.</p><?php endif; ?>
    </div></div>
  </div>
  <div class="col-lg-8"><div class="card"><div class="card-header">Order history</div><div class="table-responsive"><table class="table table-hover align-middle">
    <thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Payment</th><th class="text-end">Total</th></tr></thead>
    <tbody><?php foreach ($orders as $o): ?><tr><td><a href="<?= e(admin_url('order-view', ['id' => $o['id']])) ?>"><?= e($o['order_number']) ?></a><?= $o['customer_id'] ? '' : ' <span class="badge text-bg-light">guest</span>' ?></td><td><small><?= e(format_date($o['created_at'], true)) ?></small></td><td><?= badge($o['status']) ?></td><td><?= badge($o['payment_status']) ?></td><td class="text-end"><?= e(money($o['grand_total'])) ?></td></tr><?php endforeach; ?>
    <?php if (!$orders): ?><tr><td colspan="5" class="text-muted text-center py-4">No orders.</td></tr><?php endif; ?></tbody>
  </table></div></div></div>
</div>
<?php admin_footer();
