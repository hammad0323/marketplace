<?php
require __DIR__ . '/includes/init.php';
admin_require('customers');
$id = input_int('id');
$c = db_row('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL', [$id]);
if (!$c) { flash('danger', 'Customer not found.'); redirect('admin/customers.php'); }
if (is_post()) {
    csrf_check();
    $act = input('act');
    if ($act === 'save') {
        $email = mb_strtolower(input('email'));
        $phone = normalize_phone(input('phone'));
        if (!valid_phone($phone)) flash('danger', 'Invalid phone.');
        elseif (db_val('SELECT id FROM customers WHERE phone = ? AND id <> ?', [$phone, $id])) flash('danger', 'Another customer has this phone number.');
        elseif ($email !== '' && db_val('SELECT id FROM customers WHERE email = ? AND id <> ?', [$email, $id])) flash('danger', 'Another customer has this email.');
        else { db_exec('UPDATE customers SET name = ?, phone = ?, email = ?, notes = ?, is_active = ? WHERE id = ?', [input('name'), $phone, $email ?: null, input('notes') ?: null, input_int('is_active'), $id]); flash('success', 'Customer saved.'); }
    } elseif ($act === 'reset_password' && $c['is_registered']) {
        $new = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(9))), 0, 10);
        db_exec('UPDATE customers SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $id]);
        flash('success', 'New temporary password: ' . $new . ' — share it with the customer securely.');
    }
    redirect('admin/customer-view.php?id=' . $id);
}
$orders = db_all("SELECT * FROM orders WHERE customer_id = ? AND status <> 'draft' ORDER BY id DESC LIMIT 50", [$id]);
$addresses = db_all('SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC', [$id]);
$lists = db_all('SELECT g.*, (SELECT COUNT(*) FROM grocery_list_items WHERE list_id = g.id) AS items FROM grocery_lists g WHERE customer_id = ?', [$id]);
$stats = db_row("SELECT COUNT(*) n, COALESCE(SUM(total),0) t, COALESCE(AVG(total),0) a FROM orders WHERE customer_id = ? AND status NOT IN ('draft','cancelled')", [$id]);
admin_header($c['name'], 'customers');
?>
<div class="row g-3">
  <div class="col-lg-4">
    <form method="post" class="card mb-3"><div class="card-header">Profile</div><div class="card-body">
      <?= csrf_field() ?><input type="hidden" name="act" value="save">
      <?= af_text('name', 'Name', $c['name'], ['required' => true]) ?><?= af_text('phone', 'Phone', $c['phone'], ['required' => true]) ?><?= af_text('email', 'Email', $c['email'], ['type' => 'email']) ?>
      <?= af_textarea('notes', 'Internal notes', $c['notes'], ['rows' => 2]) ?><?= af_check('is_active', 'Account active', $c['is_active']) ?>
      <p class="small text-muted mb-0"><?= $c['is_registered'] ? 'Registered account' : 'Guest / POS customer (no login)' ?> · source: <?= e($c['source']) ?> · joined <?= fmt_date($c['created_at']) ?></p>
    </div><div class="card-footer bg-white d-flex gap-2"><button class="btn btn-brand btn-sm">Save</button>
      <a href="<?= e(whatsapp_link($c['phone'])) ?>" target="_blank" rel="noopener" class="btn btn-success btn-sm"><i class="bi bi-whatsapp"></i> WhatsApp</a>
      <?php if (admin_can('pos')): ?><a href="<?= aurl('pos.php') ?>" class="btn btn-outline-secondary btn-sm">New POS sale</a><?php endif; ?></div></form>
    <?php if ($c['is_registered']): ?><form method="post" data-confirm="Generate a new temporary password?"><?= csrf_field() ?><input type="hidden" name="act" value="reset_password"><button class="btn btn-sm btn-outline-danger w-100 mb-3">Reset password</button></form><?php endif; ?>
    <div class="card mb-3"><div class="card-header">Addresses</div><ul class="list-group list-group-flush small">
      <?php foreach ($addresses as $a): ?><li class="list-group-item"><strong><?= e($a['label']) ?></strong><?= $a['is_default'] ? ' <span class="badge bg-success">Default</span>' : '' ?><br><?= e($a['address_line']) ?>, <?= e($a['area']) ?>, <?= e($a['city']) ?></li><?php endforeach; ?>
      <?php if (!$addresses): ?><li class="list-group-item text-muted">No saved addresses.</li><?php endif; ?></ul></div>
    <div class="card"><div class="card-header">Rashan lists</div><ul class="list-group list-group-flush small">
      <?php foreach ($lists as $l): ?><li class="list-group-item d-flex justify-content-between"><span><?= e($l['name']) ?> (<?= (int) $l['items'] ?> items)</span><span><?= $l['monthly_budget'] ? money($l['monthly_budget']) : '' ?></span></li><?php endforeach; ?>
      <?php if (!$lists): ?><li class="list-group-item text-muted">None.</li><?php endif; ?></ul></div>
  </div>
  <div class="col-lg-8">
    <div class="row g-2 mb-3"><div class="col"><div class="kpi"><i class="bi bi-bag"></i><div><small>Orders</small><strong><?= (int) $stats['n'] ?></strong></div></div></div>
      <div class="col"><div class="kpi"><i class="bi bi-cash"></i><div><small>Total spent</small><strong><?= money($stats['t']) ?></strong></div></div></div>
      <div class="col"><div class="kpi"><i class="bi bi-graph-up"></i><div><small>Average order</small><strong><?= money($stats['a']) ?></strong></div></div></div></div>
    <div class="card"><div class="card-header">Order history</div><div class="table-responsive"><table class="table table-hover mb-0">
      <thead><tr><th>Order</th><th>Date</th><th>Type</th><th class="text-end">Total</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($orders as $o): ?><tr onclick="location='<?= aurl('order-view.php?id=' . $o['id']) ?>'" style="cursor:pointer"><td class="fw-semibold"><?= e($o['order_number']) ?></td><td><?= fmt_date($o['created_at']) ?></td><td><?= $o['order_type'] === 'rashan' ? 'Rashan' : 'Regular' ?> · <?= $o['channel'] === 'pos' ? 'POS' : 'Web' ?></td><td class="text-end"><?= money($o['total']) ?></td><td><?= status_badge($o['status']) ?></td></tr><?php endforeach; ?>
      <?php if (!$orders): ?><tr><td colspan="5" class="text-muted p-3">No orders.</td></tr><?php endif; ?>
      </tbody></table></div></div>
  </div>
</div>
<?php admin_footer();
