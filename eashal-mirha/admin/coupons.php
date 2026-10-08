<?php
require __DIR__ . '/includes/admin.php';
require_section('coupons');

$id = (int)get('id');
$c = $id ? row('SELECT * FROM coupons WHERE id = ?', [$id]) : null;
$d = $c ?: ['code' => '', 'type' => 'percent', 'value' => '', 'min_order' => 0, 'max_uses' => 0, 'expires_at' => '', 'status' => 1];

if (is_post()) {
    require_csrf();
    if (post('do') === 'delete') {
        q('DELETE FROM coupons WHERE id = ?', [(int)post('id')]);
        flash('success', 'Coupon deleted.');
        redirect('admin/coupons');
    }
    $code = strtoupper(preg_replace('~[^A-Z0-9_-]~i', '', post('code')));
    $type = post('type') === 'fixed' ? 'fixed' : 'percent';
    $value = (float)post('value');
    if ($code === '' || $value <= 0) {
        flash('error', 'Enter a code and a value greater than zero.');
    } elseif (val('SELECT COUNT(*) FROM coupons WHERE code = ? AND id <> ?', [$code, $id])) {
        flash('error', 'That code already exists.');
    } elseif ($type === 'percent' && $value > 100) {
        flash('error', 'Percentage cannot exceed 100.');
    } else {
        $vals = [$code, $type, $value, (float)post('min_order'), (int)post('max_uses'), post('expires_at') ?: null, post('status') === '1' ? 1 : 0];
        if ($id) q('UPDATE coupons SET code=?, type=?, value=?, min_order=?, max_uses=?, expires_at=?, status=? WHERE id = ?', array_merge($vals, [$id]));
        else q('INSERT INTO coupons (code, type, value, min_order, max_uses, expires_at, status) VALUES (?,?,?,?,?,?,?)', $vals);
        flash('success', 'Coupon saved.');
        redirect('admin/coupons');
    }
}

$list = rows('SELECT * FROM coupons ORDER BY id DESC');
admin_header('Coupons', 'coupons');
?>
<div class="grid-2 wide-left">
  <div class="card"><div class="table-wrap"><table class="table">
    <thead><tr><th>Code</th><th>Discount</th><th>Min order</th><th>Used</th><th>Expires</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $cp): $expired = $cp['expires_at'] && strtotime($cp['expires_at'] . ' 23:59:59') < time(); ?>
      <tr>
        <td><strong class="code-tag"><?= e($cp['code']) ?></strong></td>
        <td><?= $cp['type'] === 'percent' ? (float)$cp['value'] . '%' : money($cp['value']) ?></td>
        <td><?= money($cp['min_order']) ?></td>
        <td><?= (int)$cp['used'] ?><?= $cp['max_uses'] ? ' / ' . (int)$cp['max_uses'] : '' ?></td>
        <td class="<?= $expired ? 'text-danger' : 'muted' ?>"><?= $cp['expires_at'] ? date('d M Y', strtotime($cp['expires_at'])) : 'Never' ?></td>
        <td><?= $cp['status'] && !$expired ? '<span class="badge badge-delivered">Active</span>' : '<span class="badge">Inactive</span>' ?></td>
        <td class="actions"><a class="icon" href="?id=<?= $cp['id'] ?>"><?= aicon('edit') ?></a>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><button class="icon danger" name="id" value="<?= $cp['id'] ?>" data-confirm="Delete coupon <?= e($cp['code']) ?>?"><?= aicon('trash') ?></button></form></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="7" class="empty">No coupons yet.</td></tr><?php endif; ?>
    </tbody>
  </table></div></div>
  <form method="post" class="card">
    <?= csrf_field() ?>
    <div class="card__head"><h3><?= $id ? 'Edit coupon' : 'New coupon' ?></h3><?php if ($id): ?><a class="link sm" href="<?= url('admin/coupons') ?>">+ New</a><?php endif; ?></div>
    <?= f_text('code', 'Coupon code *', $d['code'], ['attrs' => 'required style="text-transform:uppercase"']) ?>
    <div class="row-2">
      <?= f_select('type', 'Type', $d['type'], ['percent' => 'Percentage (%)', 'fixed' => 'Fixed amount']) ?>
      <?= f_text('value', 'Value *', $d['value'], ['type' => 'number', 'attrs' => 'step="0.01" min="0" required']) ?>
    </div>
    <div class="row-2">
      <?= f_text('min_order', 'Minimum order', $d['min_order'], ['type' => 'number', 'attrs' => 'min="0"']) ?>
      <?= f_text('max_uses', 'Usage limit (0 = unlimited)', $d['max_uses'], ['type' => 'number', 'attrs' => 'min="0"']) ?>
    </div>
    <?= f_text('expires_at', 'Expiry date (optional)', $d['expires_at'], ['type' => 'date']) ?>
    <?= f_switch('status', 'Active', $d['status']) ?>
    <button class="btn btn-primary btn-block">Save Coupon</button>
  </form>
</div>
<?php admin_footer();
