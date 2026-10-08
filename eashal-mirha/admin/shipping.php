<?php
require __DIR__ . '/includes/admin.php';
require_section('shipping');

if (is_post()) {
    require_csrf();
    switch (post('do')) {
        case 'general':
            save_posted_settings(['shipping_flat', 'free_shipping_min', 'shipping_note']);
            flash('success', 'Delivery settings saved.');
            break;
        case 'zone':
            $city = trim(post('city'));
            if ($city === '') { flash('error', 'City name is required.'); break; }
            $vals = [$city, (float)post('charge'), post('free_above') === '' ? null : (float)post('free_above'), post('delivery_days'), post('status') === '1' ? 1 : 0];
            if ($zid = (int)post('id')) q('UPDATE shipping_zones SET city=?, charge=?, free_above=?, delivery_days=?, status=? WHERE id = ?', array_merge($vals, [$zid]));
            else q('INSERT INTO shipping_zones (city, charge, free_above, delivery_days, status) VALUES (?,?,?,?,?)', $vals);
            flash('success', 'City rate saved.');
            break;
        case 'delete':
            q('DELETE FROM shipping_zones WHERE id = ?', [(int)post('id')]);
            flash('success', 'City rate removed.');
            break;
    }
    redirect('admin/shipping');
}
$zones = rows('SELECT * FROM shipping_zones ORDER BY city');
$edit = get('id') ? row('SELECT * FROM shipping_zones WHERE id = ?', [(int)get('id')]) : null;
admin_header('Delivery Charges', 'shipping');
?>
<div class="grid-2">
  <form method="post" class="card">
    <?= csrf_field() ?><input type="hidden" name="do" value="general">
    <div class="card__head"><h3>Default delivery (all other cities)</h3></div>
    <div class="row-2">
      <?= f_text('shipping_flat', 'Delivery charge', setting('shipping_flat', 0), ['type' => 'number', 'attrs' => 'min="0"', 'help' => '0 = free delivery everywhere']) ?>
      <?= f_text('free_shipping_min', 'Free delivery on orders above', setting('free_shipping_min', 0), ['type' => 'number', 'attrs' => 'min="0"', 'help' => '0 = never free']) ?>
    </div>
    <?= f_text('shipping_note', 'Delivery note (product page)', setting('shipping_note')) ?>
    <p class="muted sm">COD fee is set in <a class="link" href="<?= url('admin/payments') ?>">Payments</a>.</p>
    <button class="btn btn-primary">Save</button>
  </form>

  <form method="post" class="card">
    <?= csrf_field() ?><input type="hidden" name="do" value="zone"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <div class="card__head"><h3><?= $edit ? 'Edit city rate' : 'Add a city-specific rate' ?></h3><?php if ($edit): ?><a class="link sm" href="<?= url('admin/shipping') ?>">+ New</a><?php endif; ?></div>
    <div class="row-2">
      <?= f_text('city', 'City *', $edit['city'] ?? '', ['attrs' => 'required']) ?>
      <?= f_text('charge', 'Delivery charge', $edit['charge'] ?? 0, ['type' => 'number', 'attrs' => 'min="0"']) ?>
    </div>
    <div class="row-2">
      <?= f_text('free_above', 'Free above (optional)', $edit['free_above'] ?? '', ['type' => 'number', 'attrs' => 'min="0"']) ?>
      <?= f_text('delivery_days', 'Delivery time', $edit['delivery_days'] ?? '', ['help' => 'e.g. 2-3 days']) ?>
    </div>
    <?= f_switch('status', 'Active', $edit['status'] ?? 1) ?>
    <button class="btn btn-primary">Save City Rate</button>
  </form>
</div>
<div class="card">
  <div class="card__head"><h3>City rates</h3><span class="muted sm">Cities appear as suggestions at checkout. Matching is case-insensitive.</span></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>City</th><th>Charge</th><th>Free above</th><th>Delivery time</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($zones as $z): ?>
      <tr><td><strong><?= e($z['city']) ?></strong></td><td><?= (float)$z['charge'] > 0 ? money($z['charge']) : 'Free' ?></td><td><?= $z['free_above'] !== null ? money($z['free_above']) : '—' ?></td><td><?= e($z['delivery_days']) ?></td>
        <td><?= $z['status'] ? '<span class="badge badge-delivered">Active</span>' : '<span class="badge">Off</span>' ?></td>
        <td class="actions"><a class="icon" href="?id=<?= $z['id'] ?>"><?= aicon('edit') ?></a><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><button class="icon danger" name="id" value="<?= $z['id'] ?>" data-confirm="Remove <?= e($z['city']) ?>?"><?= aicon('trash') ?></button></form></td></tr>
    <?php endforeach; ?>
    <?php if (!$zones): ?><tr><td colspan="6" class="empty">No city rates — the default charge applies everywhere.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php admin_footer();
