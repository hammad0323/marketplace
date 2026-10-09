<?php
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin('coupons.manage');
$errors = [];
if (is_post()) {
    csrf_check();
    if (post('action') === 'delete') {
        db_exec('DELETE FROM coupons WHERE id = ?', [(int)post('id')]);
        audit('coupon_delete', 'coupon', (int)post('id'));
        flash('success', 'Coupon deleted.');
        redirect(admin_url('coupons'));
    }
    $cid = (int)post('id');
    $d = [
        'code' => strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', post('code'))),
        'description' => mb_substr(post('description'), 0, 255) ?: null,
        'type' => in_list(post('type'), ['percent', 'fixed', 'free_shipping'], 'percent'),
        'value' => to_money(post('value')) ?? 0,
        'min_order' => to_money(post('min_order')),
        'max_discount' => to_money(post('max_discount')),
        'usage_limit' => post('usage_limit') !== '' ? max(1, (int)post('usage_limit')) : null,
        'per_customer_limit' => post('per_customer_limit') !== '' ? max(1, (int)post('per_customer_limit')) : null,
        'starts_at' => post('starts_at') ? date('Y-m-d H:i:s', strtotime(post('starts_at'))) : null,
        'ends_at' => post('ends_at') ? date('Y-m-d H:i:s', strtotime(post('ends_at'))) : null,
        'status' => post('status') === 'inactive' ? 'inactive' : 'active',
    ];
    if (strlen($d['code']) < 3) $errors[] = 'Code must be at least 3 characters.';
    if (db_val('SELECT id FROM coupons WHERE code = ? AND id <> ?', [$d['code'], $cid])) $errors[] = 'Code already exists.';
    if ($d['type'] === 'percent' && ($d['value'] <= 0 || $d['value'] > 100)) $errors[] = 'Percentage must be between 1 and 100.';
    if ($d['type'] === 'fixed' && $d['value'] <= 0) $errors[] = 'Enter a discount amount.';
    if (!$errors) {
        if ($cid) db_exec('UPDATE coupons SET ' . implode(', ', array_map(fn($k) => "$k = ?", array_keys($d))) . ' WHERE id = ?', array_merge(array_values($d), [$cid]));
        else $cid = db_insert('INSERT INTO coupons (' . implode(',', array_keys($d)) . ') VALUES (' . db_in($d) . ')', array_values($d));
        audit('coupon_save', 'coupon', $cid, $d);
        flash('success', 'Coupon saved.');
        redirect(admin_url('coupons'));
    }
}
$rows = db_all('SELECT c.*, (SELECT COALESCE(SUM(discount),0) FROM coupon_usage u WHERE u.coupon_id = c.id) given FROM coupons c ORDER BY id DESC');
$edit = get('edit') ? db_one('SELECT * FROM coupons WHERE id = ?', [(int)get('edit')]) : ($errors ? $_POST : null);
$admin_title = 'Coupons';
require __DIR__ . '/partials/header.php';
$ev = fn($k, $d = '') => $edit[$k] ?? $d;
?>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<div class="row g-3">
  <div class="col-xl-8"><div class="card"><div class="table-responsive"><table class="table mb-0">
    <thead><tr><th>Code</th><th>Discount</th><th>Conditions</th><th>Used</th><th>Valid</th><th>Status</th><th></th></tr></thead>
    <tbody><?php foreach ($rows as $c): ?>
      <tr><td class="fw-semibold"><?= e($c['code']) ?><div class="small text-muted"><?= e($c['description']) ?></div></td>
        <td><?= $c['type'] === 'percent' ? (float)$c['value'] . '%' . ($c['max_discount'] ? ' (max ' . money($c['max_discount']) . ')' : '') : ($c['type'] === 'fixed' ? money($c['value']) : 'Free delivery') ?></td>
        <td class="small"><?= $c['min_order'] ? 'Min ' . money($c['min_order']) : '—' ?><?= $c['per_customer_limit'] ? '<br>' . (int)$c['per_customer_limit'] . ' per customer' : '' ?></td>
        <td class="small"><?= (int)$c['used_count'] ?><?= $c['usage_limit'] ? ' / ' . (int)$c['usage_limit'] : '' ?><br><?= money($c['given']) ?> given</td>
        <td class="small"><?= e($c['starts_at'] ? substr($c['starts_at'], 0, 10) : 'now') ?> → <?= e($c['ends_at'] ? substr($c['ends_at'], 0, 10) : 'no end') ?></td>
        <td><?= status_badge($c['status']) ?></td>
        <td class="text-nowrap"><a class="btn btn-sm btn-light" href="?edit=<?= (int)$c['id'] ?>"><i class="bi bi-pencil"></i></a>
          <form method="post" class="d-inline" data-confirm="Delete coupon <?= e($c['code']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form></td></tr>
    <?php endforeach; ?></tbody></table></div></div></div>
  <div class="col-xl-4"><div class="card"><div class="card-header"><?= $edit ? 'Edit coupon' : 'New coupon' ?></div><div class="card-body">
    <form method="post" novalidate><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$ev('id', 0) ?>">
      <?= f_text('code', 'Code', $ev('code'), ['required' => true]) ?>
      <?= f_text('description', 'Internal description', $ev('description')) ?>
      <div class="row"><div class="col-6"><?= f_select('type', 'Type', ['percent' => 'Percentage', 'fixed' => 'Fixed amount', 'free_shipping' => 'Free delivery'], $ev('type', 'percent')) ?></div>
      <div class="col-6"><?= f_text('value', 'Value', $ev('value'), ['type' => 'number', 'step' => '0.01']) ?></div>
      <div class="col-6"><?= f_text('min_order', 'Minimum order', $ev('min_order'), ['type' => 'number']) ?></div>
      <div class="col-6"><?= f_text('max_discount', 'Max discount', $ev('max_discount'), ['type' => 'number']) ?></div>
      <div class="col-6"><?= f_text('usage_limit', 'Total uses', $ev('usage_limit'), ['type' => 'number']) ?></div>
      <div class="col-6"><?= f_text('per_customer_limit', 'Per customer', $ev('per_customer_limit'), ['type' => 'number']) ?></div>
      <div class="col-6"><?= f_text('starts_at', 'Starts', $ev('starts_at') ? date('Y-m-d\TH:i', strtotime($ev('starts_at'))) : '', ['type' => 'datetime-local']) ?></div>
      <div class="col-6"><?= f_text('ends_at', 'Ends', $ev('ends_at') ? date('Y-m-d\TH:i', strtotime($ev('ends_at'))) : '', ['type' => 'datetime-local']) ?></div></div>
      <?= f_select('status', 'Status', ['active' => 'Active', 'inactive' => 'Inactive'], $ev('status', 'active')) ?>
      <button class="btn btn-primary">Save coupon</button> <?php if ($edit): ?><a class="btn btn-light" href="<?= e(admin_url('coupons')) ?>">New</a><?php endif; ?>
    </form></div></div></div>
</div>
<?php require __DIR__ . '/partials/footer.php';
