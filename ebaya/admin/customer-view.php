<?php
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin('customers.manage');
$id = (int)get('id');
$c = db_one('SELECT * FROM customers WHERE id = ?', [$id]);
if (!$c) { flash('danger', 'Customer not found.'); redirect(admin_url('customers')); }
if (is_post()) {
    csrf_check();
    if (post('action') === 'status') {
        $s = post('status') === 'disabled' ? 'disabled' : 'active';
        db_exec('UPDATE customers SET status = ? WHERE id = ?', [$s, $id]);
        audit('customer_status', 'customer', $id, ['status' => $s]);
        flash('success', 'Account ' . ($s === 'disabled' ? 'disabled' : 'enabled') . '.');
    } elseif (post('action') === 'update') {
        $name = mb_substr(post('name'), 0, 120);
        $phone = post('phone');
        if (v_len($name, 2, 120) && ($phone === '' || v_phone($phone))) {
            db_exec('UPDATE customers SET name = ?, phone = ? WHERE id = ?', [$name, $phone, $id]);
            audit('customer_update', 'customer', $id);
            flash('success', 'Customer updated.');
        } else flash('danger', 'Please enter a valid name and phone.');
    } elseif (post('action') === 'reset') {
        $token = password_reset_create('customer', $id);
        send_mail($c['email'], 'Reset your Ebaya password', '<p>A password reset was requested for your account.</p><p><a href="' . e(abs_url('account/reset-password?token=' . $token)) . '">Choose a new password</a> (valid for one hour).</p>');
        audit('customer_reset_sent', 'customer', $id);
        flash('success', 'Password reset link sent (if email sending is enabled).');
    }
    redirect(admin_url('customer-view?id=' . $id));
}
$orders = db_all('SELECT * FROM orders WHERE customer_id = ? OR (customer_id IS NULL AND email = ?) ORDER BY id DESC', [$id, $c['email']]);
$addresses = db_all('SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC', [$id]);
$spent = array_sum(array_map(fn($o) => in_array($o['payment_status'], ['paid', 'partially_refunded'], true) ? $o['grand_total'] - $o['refunded_total'] : 0, $orders));
$wish = db_col('SELECT p.name FROM wishlist_items wi JOIN wishlists w ON w.id = wi.wishlist_id JOIN products p ON p.id = wi.product_id WHERE w.customer_id = ?', [$id]);
$admin_title = $c['name'];
require __DIR__ . '/partials/header.php';
?>
<a href="<?= e(admin_url('customers')) ?>" class="btn btn-sm btn-light mb-3"><i class="bi bi-arrow-left"></i> Customers</a>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card mb-3"><div class="card-body">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update">
        <?= f_text('name', 'Name', $c['name']) ?><?= f_text('phone', 'Phone', $c['phone']) ?>
        <p class="small mb-2">Email: <?= e($c['email']) ?><br>Joined: <?= e($c['created_at']) ?><br>Last login: <?= e($c['last_login_at'] ?? '—') ?><br>Marketing: <?= $c['marketing_opt_in'] ? 'opted in' : 'no' ?></p>
        <button class="btn btn-sm btn-primary">Save</button></form>
      <hr><p class="mb-1">Status: <?= status_badge($c['status']) ?></p>
      <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="<?= $c['status'] === 'active' ? 'disabled' : 'active' ?>"><button class="btn btn-sm btn-outline-<?= $c['status'] === 'active' ? 'danger' : 'success' ?>"><?= $c['status'] === 'active' ? 'Disable account' : 'Enable account' ?></button></form>
      <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><button class="btn btn-sm btn-outline-secondary">Send password reset</button></form>
    </div></div>
    <div class="card mb-3"><div class="card-header">Addresses</div><ul class="list-group list-group-flush">
      <?php foreach ($addresses as $a): ?><li class="list-group-item small"><?= $a['is_default'] ? '<span class="badge text-bg-light">Default</span> ' : '' ?><?= e($a['full_name']) ?>, <?= e($a['address_line1']) ?>, <?= e($a['city']) ?> · <?= e($a['phone']) ?></li><?php endforeach; ?>
      <?php if (!$addresses): ?><li class="list-group-item small text-muted">None saved.</li><?php endif; ?></ul></div>
    <div class="card"><div class="card-header">Wishlist</div><div class="card-body small"><?= $wish ? e(implode(', ', $wish)) : '<span class="text-muted">Empty</span>' ?></div></div>
  </div>
  <div class="col-lg-8"><div class="card"><div class="card-header">Purchase history · <?= count($orders) ?> orders · <?= money($spent) ?> paid</div>
    <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Payment</th><th class="text-end">Total</th></tr></thead><tbody>
    <?php foreach ($orders as $o): ?><tr><td><a href="<?= e(admin_url('order-view?id=' . $o['id'])) ?>"><?= e($o['order_number']) ?></a><?= $o['customer_id'] ? '' : ' <span class="badge text-bg-light">guest</span>' ?></td><td class="small"><?= e($o['created_at']) ?></td><td><?= status_badge($o['status']) ?></td><td><?= status_badge($o['payment_status']) ?></td><td class="text-end"><?= money($o['grand_total']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div></div>
</div>
<?php require __DIR__ . '/partials/footer.php';
