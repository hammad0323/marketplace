<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('coupons.manage');
$editId = input_int('edit', 0, 'get');
$errors = [];
if (is_post()) {
    require_csrf();
    $id = input_int('id');
    if (input('action') === 'delete') {
        if (db_val('SELECT COUNT(*) FROM coupon_usage WHERE coupon_id = ?', [$id])) {
            db_exec('UPDATE coupons SET is_active = 0 WHERE id = ?', [$id]);
            flash('warning', 'Coupon has been used on orders, so it was deactivated instead of deleted.');
        } else {
            db_exec('DELETE FROM coupons WHERE id = ?', [$id]);
            flash('success', 'Coupon deleted.');
        }
        audit_log('coupon_deleted', 'coupon', $id);
        redirect(admin_url('coupons'));
    }
    $d = [
        'code' => strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', input('code'))),
        'description' => mb_substr(input('description'), 0, 255) ?: null,
        'discount_type' => in_array(input('discount_type'), ['percent', 'fixed', 'free_shipping'], true) ? input('discount_type') : 'percent',
        'discount_value' => max(0, (float) input_float('discount_value', 0)),
        'min_order_amount' => max(0, (float) input_float('min_order_amount', 0)),
        'max_discount' => input_float('max_discount'),
        'usage_limit' => input('usage_limit') !== '' ? max(1, input_int('usage_limit')) : null,
        'per_customer_limit' => input('per_customer_limit') !== '' ? max(1, input_int('per_customer_limit')) : null,
        'starts_at' => input('starts_at') ? date('Y-m-d H:i:s', strtotime(input('starts_at'))) : null,
        'ends_at' => input('ends_at') ? date('Y-m-d H:i:s', strtotime(input('ends_at'))) : null,
        'is_active' => input_bool('is_active'),
    ];
    if (strlen($d['code']) < 3) {
        $errors[] = 'Code must be at least 3 characters (letters, numbers, - and _).';
    }
    if (db_val('SELECT COUNT(*) FROM coupons WHERE code = ? AND id <> ?', [$d['code'], $id])) {
        $errors[] = 'That code already exists.';
    }
    if ($d['discount_type'] === 'percent' && ($d['discount_value'] <= 0 || $d['discount_value'] > 100)) {
        $errors[] = 'Percentage must be between 0 and 100.';
    }
    if ($d['discount_type'] === 'fixed' && $d['discount_value'] <= 0) {
        $errors[] = 'Fixed discount must be greater than zero.';
    }
    if ($d['starts_at'] && $d['ends_at'] && $d['ends_at'] < $d['starts_at']) {
        $errors[] = 'End date must be after the start date.';
    }
    if (!$errors) {
        $id ? db_update('coupons', $d, 'id = ?', [$id]) : ($id = db_insert('coupons', $d));
        audit_log('coupon_saved', 'coupon', $id, ['code' => $d['code']]);
        flash('success', 'Coupon saved.');
        redirect(admin_url('coupons'));
    }
    $editId = $id;
    $edit = $d + ['id' => $id];
}
$rows = db_all('SELECT c.*, (SELECT COALESCE(SUM(discount_amount), 0) FROM coupon_usage u WHERE u.coupon_id = c.id) total_discount FROM coupons c ORDER BY c.created_at DESC');
$edit = $edit ?? ($editId ? db_one('SELECT * FROM coupons WHERE id = ?', [$editId]) : null);
admin_header('Coupons', 'coupons');
?>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<div class="row g-3">
  <div class="col-xl-8"><div class="card"><div class="table-responsive"><table class="table table-hover align-middle">
    <thead><tr><th>Code</th><th>Discount</th><th>Conditions</th><th>Used</th><th>Valid</th><th>Active</th><th></th></tr></thead>
    <tbody><?php foreach ($rows as $c): ?>
      <tr><td><strong><?= e($c['code']) ?></strong><br><small class="text-muted"><?= e($c['description']) ?></small></td><td><?= e(coupon_summary($c)) ?><?= $c['max_discount'] ? '<br><small>max ' . e(money($c['max_discount'])) . '</small>' : '' ?></td>
        <td><small><?= (float) $c['min_order_amount'] ? 'Min ' . e(money($c['min_order_amount'])) . '<br>' : '' ?><?= $c['per_customer_limit'] ? (int) $c['per_customer_limit'] . '× per customer' : '' ?></small></td>
        <td><?= (int) $c['times_used'] ?><?= $c['usage_limit'] ? ' / ' . (int) $c['usage_limit'] : '' ?><br><small class="text-muted"><?= e(money($c['total_discount'])) ?></small></td>
        <td><small><?= $c['starts_at'] ? e(format_date($c['starts_at'])) : 'now' ?> → <?= $c['ends_at'] ? e(format_date($c['ends_at'])) : '∞' ?></small></td>
        <td><div class="form-check form-switch"><input class="form-check-input" type="checkbox" data-toggle-url data-entity="coupon" data-field="is_active" data-id="<?= (int) $c['id'] ?>" <?= $c['is_active'] ? 'checked' : '' ?>></div></td>
        <td class="text-end text-nowrap"><a class="btn btn-sm btn-light" href="?edit=<?= (int) $c['id'] ?>"><i class="bi bi-pencil"></i></a>
          <form method="post" class="d-inline" data-confirm="Delete coupon <?= e($c['code']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form></td></tr>
    <?php endforeach; ?></tbody></table></div></div></div>
  <div class="col-xl-4"><div class="card"><div class="card-header"><?= $edit ? 'Edit coupon' : 'New coupon' ?></div><div class="card-body">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <?= f_text('code', 'Code', $edit['code'] ?? '', ['required' => true, 'style' => 'text-transform:uppercase']) ?>
      <?= f_text('description', 'Description (internal)', $edit['description'] ?? '') ?>
      <?= f_select('discount_type', 'Type', ['percent' => 'Percentage off', 'fixed' => 'Fixed amount off', 'free_shipping' => 'Free delivery'], $edit['discount_type'] ?? 'percent') ?>
      <div class="row"><div class="col-6"><?= f_number('discount_value', 'Value', $edit['discount_value'] ?? '', ['min' => 0]) ?></div><div class="col-6"><?= f_number('max_discount', 'Max discount', $edit['max_discount'] ?? '', ['min' => 0], '% coupons only') ?></div></div>
      <?= f_number('min_order_amount', 'Minimum order subtotal', $edit['min_order_amount'] ?? 0, ['min' => 0]) ?>
      <div class="row"><div class="col-6"><?= f_number('usage_limit', 'Total uses', $edit['usage_limit'] ?? '', ['min' => 1, 'step' => 1], 'Blank = unlimited') ?></div><div class="col-6"><?= f_number('per_customer_limit', 'Per customer', $edit['per_customer_limit'] ?? '', ['min' => 1, 'step' => 1]) ?></div></div>
      <div class="row"><div class="col-6"><?= f_text('starts_at', 'Starts', !empty($edit['starts_at']) ? date('Y-m-d\TH:i', strtotime($edit['starts_at'])) : '', [], null, 'datetime-local') ?></div><div class="col-6"><?= f_text('ends_at', 'Ends', !empty($edit['ends_at']) ? date('Y-m-d\TH:i', strtotime($edit['ends_at'])) : '', [], null, 'datetime-local') ?></div></div>
      <?= f_check('is_active', 'Active', (int) ($edit['is_active'] ?? 1)) ?>
      <?= f_submit() ?> <?php if ($edit): ?><a class="btn btn-light" href="<?= e(admin_url('coupons')) ?>">Cancel</a><?php endif; ?>
    </form>
  </div></div></div>
</div>
<?php admin_footer();
