<?php
require __DIR__ . '/includes/init.php';
admin_require('coupons');
if (is_post()) {
    csrf_check();
    $id = input_int('id');
    if (input('act') === 'delete') { db_exec('DELETE FROM coupons WHERE id = ?', [$id]); flash('success', 'Coupon deleted.'); redirect('admin/coupons.php'); }
    $code = strtoupper(preg_replace('/[^A-Za-z0-9_\-]/', '', input('code')));
    $type = in_array(input('discount_type'), ['percent', 'fixed', 'free_delivery'], true) ? input('discount_type') : 'percent';
    $val = input_float('discount_value', 0.0);
    if ($code === '') { flash('danger', 'Coupon code is required.'); redirect('admin/coupons.php'); }
    if (db_val('SELECT id FROM coupons WHERE code = ? AND id <> ?', [$code, $id])) { flash('danger', 'This code already exists.'); redirect('admin/coupons.php'); }
    if ($type === 'percent' && ($val <= 0 || $val > 100)) { flash('danger', 'Percentage must be between 1 and 100.'); redirect('admin/coupons.php'); }
    $d = ['code' => $code, 'description' => input('description') ?: null, 'discount_type' => $type, 'discount_value' => $val, 'min_order_amount' => input_float('min_order_amount', 0.0),
        'max_discount' => input_float('max_discount', null), 'usage_limit' => input('usage_limit') !== '' ? input_int('usage_limit') : null, 'per_customer_limit' => input('per_customer_limit') !== '' ? input_int('per_customer_limit') : null,
        'starts_at' => input('starts_at') ? date('Y-m-d H:i:s', strtotime(input('starts_at'))) : null, 'ends_at' => input('ends_at') ? date('Y-m-d H:i:s', strtotime(input('ends_at'))) : null, 'is_active' => input_int('is_active')];
    $id ? db_update_row('coupons', $d, 'id = ?', [$id]) : db_insert_row('coupons', $d);
    flash('success', 'Coupon saved.');
    redirect('admin/coupons.php');
}
$rows = db_all('SELECT c.*, (SELECT COALESCE(SUM(discount_amount),0) FROM coupon_usage WHERE coupon_id = c.id) AS given FROM coupons c ORDER BY c.id DESC');
$c = input_int('edit') ? db_row('SELECT * FROM coupons WHERE id = ?', [input_int('edit')]) : null;
$c = $c ?: ['id' => 0, 'code' => '', 'description' => '', 'discount_type' => 'percent', 'discount_value' => '', 'min_order_amount' => 0, 'max_discount' => '', 'usage_limit' => '', 'per_customer_limit' => '', 'starts_at' => '', 'ends_at' => '', 'is_active' => 1];
admin_header('Coupons & Discounts', 'coupons');
?>
<div class="row g-3"><div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>Code</th><th>Discount</th><th>Min order</th><th>Used</th><th>Validity</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): $expired = $r['ends_at'] && strtotime($r['ends_at']) < time(); ?>
  <tr><td><strong class="font-monospace"><?= e($r['code']) ?></strong><br><small class="text-muted"><?= e($r['description']) ?></small></td>
    <td><?= $r['discount_type'] === 'percent' ? (float) $r['discount_value'] . '%' . ($r['max_discount'] ? ' (max ' . money($r['max_discount']) . ')' : '') : ($r['discount_type'] === 'fixed' ? money($r['discount_value']) : 'Free delivery') ?></td>
    <td><?= money($r['min_order_amount']) ?></td><td><?= (int) $r['used_count'] ?><?= $r['usage_limit'] ? ' / ' . (int) $r['usage_limit'] : '' ?><br><small class="text-muted"><?= money($r['given']) ?> given</small></td>
    <td class="small"><?= $r['starts_at'] ? fmt_date($r['starts_at']) : 'Now' ?> – <?= $r['ends_at'] ? fmt_date($r['ends_at']) : 'No end' ?></td>
    <td><?= !$r['is_active'] ? '<span class="badge bg-secondary">Off</span>' : ($expired ? '<span class="badge bg-dark">Expired</span>' : '<span class="badge bg-success">Active</span>') ?></td>
    <td class="text-end text-nowrap"><a href="?edit=<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a> <form method="post" class="d-inline" data-confirm="Delete coupon?"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
  <?php endforeach; ?></tbody></table></div></div></div>
<div class="col-lg-4"><form method="post" class="card"><div class="card-header"><?= $c['id'] ? 'Edit coupon' : 'New coupon' ?></div><div class="card-body"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
  <?= af_text('code', 'Code *', $c['code'], ['required' => true, 'class' => 'form-control text-uppercase font-monospace']) ?><?= af_text('description', 'Description', $c['description']) ?>
  <div class="row"><div class="col-7"><?= af_select('discount_type', 'Type', ['percent' => 'Percentage %', 'fixed' => 'Fixed amount', 'free_delivery' => 'Free delivery'], $c['discount_type']) ?></div><div class="col-5"><?= af_text('discount_value', 'Value', $c['discount_value'], ['type' => 'number', 'step' => '0.01', 'min' => 0]) ?></div></div>
  <div class="row"><div class="col"><?= af_text('min_order_amount', 'Min order', $c['min_order_amount'], ['type' => 'number', 'min' => 0]) ?></div><div class="col"><?= af_text('max_discount', 'Max discount', $c['max_discount'], ['type' => 'number', 'min' => 0]) ?></div></div>
  <div class="row"><div class="col"><?= af_text('usage_limit', 'Total uses', $c['usage_limit'], ['type' => 'number', 'min' => 1, 'placeholder' => '∞']) ?></div><div class="col"><?= af_text('per_customer_limit', 'Per customer', $c['per_customer_limit'], ['type' => 'number', 'min' => 1, 'placeholder' => '∞']) ?></div></div>
  <?= af_text('starts_at', 'Starts', $c['starts_at'] ? date('Y-m-d\TH:i', strtotime($c['starts_at'])) : '', ['type' => 'datetime-local']) ?><?= af_text('ends_at', 'Ends', $c['ends_at'] ? date('Y-m-d\TH:i', strtotime($c['ends_at'])) : '', ['type' => 'datetime-local']) ?>
  <?= af_check('is_active', 'Active', $c['is_active']) ?>
</div><div class="card-footer bg-white"><button class="btn btn-brand">Save coupon</button></div></form></div></div>
<?php admin_footer();
