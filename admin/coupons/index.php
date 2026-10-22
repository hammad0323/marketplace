<?php
require __DIR__ . '/../includes/init.php';
admin_require('coupons');
$editId = input_int('edit');
$errors = [];
if (is_post()) {
    $a = input('action', 'save', 'post');
    if ($a === 'delete') {
        $id = input_int('id');
        if (db_val('SELECT 1 FROM coupon_usage WHERE coupon_id = ? LIMIT 1', [$id])) {
            db_exec('UPDATE coupons SET is_active = 0 WHERE id = ?', [$id]);
            flash('info', 'This coupon has been used on orders, so it was deactivated instead of deleted.');
        } else {
            db_exec('DELETE FROM coupons WHERE id = ?', [$id]);
            flash('success', 'Coupon deleted.');
        }
        audit_log('coupon_deleted', 'coupon', $id);
        redirect('admin/coupons/');
    }
    $id = input_int('id');
    $d = [
        'code' => strtoupper(preg_replace('/[^A-Za-z0-9\-_]/', '', input('code', '', 'post'))),
        'description' => nullable(mb_substr(input('description', '', 'post'), 0, 255)),
        'discount_type' => in_array(input('discount_type', '', 'post'), ['percent', 'fixed', 'free_shipping'], true) ? input('discount_type', '', 'post') : 'percent',
        'discount_value' => (float) input_float('discount_value', 0),
        'min_order' => input_float('min_order'),
        'max_discount' => input_float('max_discount'),
        'usage_limit' => input('usage_limit', '', 'post') === '' ? null : max(0, input_int('usage_limit')),
        'usage_limit_per_customer' => input('usage_limit_per_customer', '', 'post') === '' ? null : max(0, input_int('usage_limit_per_customer')),
        'starts_at' => input('starts_at', '', 'post') ? date('Y-m-d H:i:s', strtotime(input('starts_at', '', 'post'))) : null,
        'expires_at' => input('expires_at', '', 'post') ? date('Y-m-d H:i:s', strtotime(input('expires_at', '', 'post'))) : null,
        'is_active' => input_bool('is_active'),
    ];
    if (strlen($d['code']) < 3) $errors[] = 'Code must be at least 3 characters.';
    if (db_val('SELECT id FROM coupons WHERE code = ? AND id <> ?', [$d['code'], $id])) $errors[] = 'This code already exists.';
    if ($d['discount_type'] === 'percent' && ($d['discount_value'] <= 0 || $d['discount_value'] > 100)) $errors[] = 'Percentage must be between 1 and 100.';
    if ($d['discount_type'] === 'fixed' && $d['discount_value'] <= 0) $errors[] = 'Fixed discount must be greater than zero.';
    if (!$errors) {
        $id ? db_update('coupons', $d, 'id = ?', [$id]) : ($id = db_insert('coupons', $d));
        audit_log('coupon_saved', 'coupon', $id, $d);
        flash('success', 'Coupon saved.');
        redirect('admin/coupons/');
    }
    $editId = $id ?: -1;
}
$coupons = db_all('SELECT c.*, (SELECT COALESCE(SUM(discount_amount),0) FROM coupon_usage u WHERE u.coupon_id = c.id) given FROM coupons c ORDER BY c.id DESC');
$e = $editId > 0 ? db_row('SELECT * FROM coupons WHERE id = ?', [$editId]) : ($errors ? $_POST : ['is_active' => 1, 'discount_type' => 'percent']);
admin_header('Coupons');
?>
<div class="row g-4"><div class="col-lg-8">
<div class="card a-card"><div class="table-responsive"><table class="table a-table mb-0"><thead><tr><th>Code</th><th>Discount</th><th>Min order</th><th>Used</th><th>Valid</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($coupons as $c): ?><tr><td><strong><?= e($c['code']) ?></strong><div class="small text-muted"><?= e($c['description']) ?></div></td>
<td><?= $c['discount_type'] === 'percent' ? (float) $c['discount_value'] . '%' . ($c['max_discount'] ? ' (max ' . e(money($c['max_discount'])) . ')' : '') : ($c['discount_type'] === 'fixed' ? e(money($c['discount_value'])) : 'Free delivery') ?></td>
<td><?= $c['min_order'] ? e(money($c['min_order'])) : '—' ?></td><td><?= (int) $c['used_count'] ?><?= $c['usage_limit'] ? ' / ' . (int) $c['usage_limit'] : '' ?><div class="small text-muted"><?= e(money($c['given'])) ?> given</div></td>
<td class="small"><?= $c['starts_at'] ? e(format_date($c['starts_at'])) : 'now' ?> → <?= $c['expires_at'] ? e(format_date($c['expires_at'])) : 'no expiry' ?></td>
<td><?= a_status_toggle((bool) $c['is_active'] && (!$c['expires_at'] || strtotime($c['expires_at']) > time())) ?></td>
<td class="text-end text-nowrap"><a class="btn btn-sm btn-light" href="?edit=<?= (int) $c['id'] ?>"><i class="bi bi-pencil"></i></a> <?= a_post_button(url('admin/coupons/'), ['action' => 'delete', 'id' => $c['id']], '<i class="bi bi-trash"></i>', 'btn btn-sm btn-light text-danger', 'Delete coupon ' . $c['code'] . '?') ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
</div><div class="col-lg-4">
<div class="card a-card"><div class="card-header"><?= $editId > 0 ? 'Edit coupon' : 'New coupon' ?></div><div class="card-body">
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= max(0, $editId) ?>">
    <?= a_input('code', 'Code', $e['code'] ?? '', 'text', ['required' => true, 'style' => 'text-transform:uppercase']) ?>
    <?= a_input('description', 'Description (internal)', $e['description'] ?? '') ?>
    <?= a_select('discount_type', 'Type', ['percent' => 'Percentage', 'fixed' => 'Fixed amount', 'free_shipping' => 'Free delivery'], $e['discount_type'] ?? 'percent') ?>
    <div class="row"><div class="col-6"><?= a_input('discount_value', 'Value', $e['discount_value'] ?? '', 'number', ['step' => '0.01']) ?></div><div class="col-6"><?= a_input('max_discount', 'Max discount', $e['max_discount'] ?? '', 'number', ['step' => '0.01']) ?></div></div>
    <?= a_input('min_order', 'Minimum order', $e['min_order'] ?? '', 'number', ['step' => '0.01']) ?>
    <div class="row"><div class="col-6"><?= a_input('usage_limit', 'Total uses', $e['usage_limit'] ?? '', 'number', ['min' => 0], 'Blank = unlimited') ?></div><div class="col-6"><?= a_input('usage_limit_per_customer', 'Per customer', $e['usage_limit_per_customer'] ?? '', 'number', ['min' => 0]) ?></div></div>
    <div class="row"><div class="col-6"><?= a_input('starts_at', 'Starts', !empty($e['starts_at']) ? date('Y-m-d\TH:i', strtotime($e['starts_at'])) : '', 'datetime-local') ?></div><div class="col-6"><?= a_input('expires_at', 'Expires', !empty($e['expires_at']) ? date('Y-m-d\TH:i', strtotime($e['expires_at'])) : '', 'datetime-local') ?></div></div>
    <?= a_check('is_active', 'Active', (int) ($e['is_active'] ?? 1)) ?>
    <button class="btn btn-dark w-100">Save coupon</button><?php if ($editId > 0): ?><a class="btn btn-link w-100" href="<?= e(url('admin/coupons/')) ?>">New coupon</a><?php endif; ?>
</form></div></div>
</div></div>
<?php admin_footer();
