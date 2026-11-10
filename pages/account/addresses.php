<?php
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
$c = require_customer();
if (is_post()) {
    csrf_check();
    $act = input('act');
    $id = input_int('id');
    if ($act === 'delete') {
        db_exec('DELETE FROM customer_addresses WHERE id = ? AND customer_id = ?', [$id, $c['id']]);
        flash('success', 'Address removed.');
    } elseif ($act === 'default') {
        db_exec('UPDATE customer_addresses SET is_default = (id = ?) WHERE customer_id = ?', [$id, $c['id']]);
        flash('success', 'Default address updated.');
    } else {
        $z = delivery_zone(input_int('zone_id'));
        $line = input('address_line');
        if (!$z || mb_strlen($line) < 5 || !valid_phone(input('phone'))) {
            flash('danger', 'Please fill the area, complete address and a valid phone.');
        } else {
            $data = ['label' => mb_substr(input('label') ?: 'Home', 0, 60), 'recipient_name' => mb_substr(input('recipient_name') ?: $c['name'], 0, 120), 'phone' => normalize_phone(input('phone')),
                'address_line' => mb_substr($line, 0, 255), 'city' => $z['city'], 'area' => $z['area'], 'delivery_zone_id' => (int) $z['id'], 'landmark' => mb_substr(input('landmark'), 0, 160) ?: null];
            if ($id) db_update_row('customer_addresses', $data, 'id = ? AND customer_id = ?', [$id, $c['id']]);
            else { $data['customer_id'] = $c['id']; $data['is_default'] = db_val('SELECT COUNT(*) FROM customer_addresses WHERE customer_id = ?', [$c['id']]) ? 0 : 1; db_insert_row('customer_addresses', $data); }
            flash('success', 'Address saved.');
        }
    }
    redirect('my-account/addresses/');
}
$addresses = db_all('SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id DESC', [$c['id']]);
$edit = input_int('edit') ? db_row('SELECT * FROM customer_addresses WHERE id = ? AND customer_id = ?', [input_int('edit'), $c['id']]) : null;
$active = 'addresses'; $title = 'My Addresses';
require FS_ROOT . '/partials/account-start.php';
?>
<div class="row g-3">
  <?php foreach ($addresses as $a): ?>
  <div class="col-md-6"><div class="box h-100">
    <div class="d-flex justify-content-between"><strong><?= e($a['label']) ?></strong><?= $a['is_default'] ? '<span class="badge bg-success">Default</span>' : '' ?></div>
    <p class="small mb-2"><?= e($a['recipient_name']) ?> · <?= e($a['phone']) ?><br><?= e($a['address_line']) ?><br><?= e($a['area']) ?>, <?= e($a['city']) ?><?= $a['landmark'] ? '<br>Near ' . e($a['landmark']) : '' ?></p>
    <div class="d-flex gap-2">
      <a href="?edit=<?= (int) $a['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
      <?php if (!$a['is_default']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="default"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="btn btn-sm btn-outline-brand">Make default</button></form><?php endif; ?>
      <form method="post" onsubmit="return confirm('Delete this address?')"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
    </div>
  </div></div>
  <?php endforeach; ?>
</div>
<form method="post" class="box mt-3">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
  <h2 class="box-title"><?= $edit ? 'Edit address' : 'Add a new address' ?></h2>
  <div class="row g-2">
    <div class="col-md-4"><label class="form-label" for="aLabel">Label</label><input id="aLabel" name="label" class="form-control" placeholder="Home / Office" value="<?= e($edit['label'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label" for="aName">Recipient</label><input id="aName" name="recipient_name" class="form-control" value="<?= e($edit['recipient_name'] ?? $c['name']) ?>"></div>
    <div class="col-md-4"><label class="form-label" for="aPhone">Phone</label><input id="aPhone" name="phone" class="form-control" required value="<?= e($edit['phone'] ?? $c['phone']) ?>"></div>
    <div class="col-md-4"><label class="form-label" for="aZone">Area</label><select id="aZone" name="zone_id" class="form-select" required><option value="">Select…</option>
      <?php foreach (delivery_zones() as $z): ?><option value="<?= (int) $z['id'] ?>" <?= (int) ($edit['delivery_zone_id'] ?? 0) === (int) $z['id'] ? 'selected' : '' ?>><?= e($z['area'] . ', ' . $z['city']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-8"><label class="form-label" for="aLine">Complete address</label><input id="aLine" name="address_line" class="form-control" required value="<?= e($edit['address_line'] ?? '') ?>"></div>
    <div class="col-12"><label class="form-label" for="aLand">Landmark</label><input id="aLand" name="landmark" class="form-control" value="<?= e($edit['landmark'] ?? '') ?>"></div>
  </div>
  <button class="btn btn-brand mt-3">Save address</button> <?php if ($edit): ?><a href="<?= url('my-account/addresses/') ?>" class="btn btn-link mt-3">Cancel</a><?php endif; ?>
</form>
<?php require FS_ROOT . '/partials/account-end.php';
