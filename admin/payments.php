<?php
require __DIR__ . '/includes/init.php';
admin_require('delivery');
if (is_post()) {
    csrf_check();
    $id = input_int('id');
    if (input('act') === 'settings') { setting_set('require_payment_reference', input_int('require_payment_reference')); flash('success', 'Saved.'); redirect('admin/payments.php'); }
    $code = preg_replace('/[^a-z0-9_]/', '', strtolower(input('code')));
    if ($code === '' || input('name') === '') { flash('danger', 'Code and name are required.'); redirect('admin/payments.php'); }
    if (db_val('SELECT id FROM payment_methods WHERE code = ? AND id <> ?', [$code, $id])) { flash('danger', 'Code already exists.'); redirect('admin/payments.php'); }
    $d = ['code' => $code, 'name' => input('name'), 'instructions' => input('instructions') ?: null, 'applies_to' => in_array(input('applies_to'), ['both', 'delivery', 'pickup', 'pos'], true) ? input('applies_to') : 'both',
        'requires_reference' => input_int('requires_reference'), 'is_active' => input_int('is_active'), 'show_in_pos' => input_int('show_in_pos'), 'sort_order' => input_int('sort_order')];
    $id ? db_update_row('payment_methods', $d, 'id = ?', [$id]) : db_insert_row('payment_methods', $d);
    flash('success', 'Payment method saved.');
    redirect('admin/payments.php');
}
$rows = db_all('SELECT * FROM payment_methods ORDER BY sort_order, id');
$m = input_int('edit') ? db_row('SELECT * FROM payment_methods WHERE id = ?', [input_int('edit')]) : ['id' => 0, 'code' => '', 'name' => '', 'instructions' => '', 'applies_to' => 'both', 'requires_reference' => 0, 'is_active' => 1, 'show_in_pos' => 1, 'sort_order' => 0];
$ap = ['both' => 'Delivery & pickup', 'delivery' => 'Delivery only', 'pickup' => 'Pickup only', 'pos' => 'POS only'];
admin_header('Payment Methods', 'payments');
?>
<div class="row g-3"><div class="col-lg-7"><div class="card"><table class="table align-middle mb-0"><thead><tr><th>Method</th><th>Available for</th><th>POS</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><strong><?= e($r['name']) ?></strong> <code class="small"><?= e($r['code']) ?></code><?= $r['instructions'] ? '<div class="small text-muted text-truncate" style="max-width:300px">' . e($r['instructions']) . '</div>' : '' ?></td><td class="small"><?= e($ap[$r['applies_to']]) ?></td><td><?= $r['show_in_pos'] ? '✓' : '' ?></td>
  <td><?= $r['is_active'] ? '<span class="badge bg-success">On</span>' : '<span class="badge bg-secondary">Off</span>' ?></td><td class="text-end"><a href="?edit=<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a></td></tr><?php endforeach; ?>
</tbody></table></div>
<form method="post" class="card mt-3"><div class="card-body"><?= csrf_field() ?><input type="hidden" name="act" value="settings"><?= af_check('require_payment_reference', 'Require transaction ID for Easypaisa / JazzCash / bank transfer at checkout', setting('require_payment_reference') === '1') ?><button class="btn btn-sm btn-outline-brand">Save</button></div></form>
<div class="alert alert-light border small mt-3">Online payment is never required to place an order. For an automatic Easypaisa/JazzCash gateway, add the merchant credentials in <code>includes/config.php</code> and connect the gateway callback; until then these methods work as manual transfers with a transaction ID.</div></div>
<div class="col-lg-5"><form method="post" class="card"><div class="card-header"><?= $m['id'] ? 'Edit' : 'Add' ?> payment method</div><div class="card-body"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
  <div class="row"><div class="col-5"><?= af_text('code', 'Code', $m['code'], ['required' => true, 'pattern' => '[a-z0-9_]+']) ?></div><div class="col-7"><?= af_text('name', 'Name', $m['name'], ['required' => true]) ?></div></div>
  <?= af_textarea('instructions', 'Instructions / account details', $m['instructions'], ['rows' => 4]) ?><?= af_select('applies_to', 'Available for', $ap, $m['applies_to']) ?>
  <?= af_text('sort_order', 'Order', $m['sort_order'], ['type' => 'number']) ?><?= af_check('requires_reference', 'Ask for transaction ID', $m['requires_reference']) ?><?= af_check('show_in_pos', 'Show in POS', $m['show_in_pos']) ?><?= af_check('is_active', 'Active', $m['is_active']) ?>
</div><div class="card-footer bg-white"><button class="btn btn-brand">Save</button></div></form></div></div>
<?php admin_footer();
