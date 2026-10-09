<?php
/** Delivery zones (cities/provinces), rates per method & fulfilment type, COD rules. */
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin('shipping.manage');
if (is_post()) {
    csrf_check();
    $act = post('action');
    try {
        switch ($act) {
            case 'save_zone':
                $zid = (int)post('id');
                $d = [mb_substr(post('name'), 0, 120), post('is_default') ? 1 : 0, post('cod_enabled') ? 1 : 0, to_money(post('cod_fee')) ?? 0, to_money(post('cod_max_order')), post('status') === 'inactive' ? 'inactive' : 'active', (int)post('sort_order')];
                if ($d[0] === '') throw new InvalidArgumentException('Zone name is required.');
                db_tx(function () use (&$zid, $d) {
                    if ($d[1]) db_exec('UPDATE shipping_zones SET is_default = 0');
                    if ($zid) db_exec('UPDATE shipping_zones SET name=?, is_default=?, cod_enabled=?, cod_fee=?, cod_max_order=?, status=?, sort_order=? WHERE id = ?', array_merge($d, [$zid]));
                    else $zid = db_insert('INSERT INTO shipping_zones (name, is_default, cod_enabled, cod_fee, cod_max_order, status, sort_order) VALUES (?,?,?,?,?,?,?)', $d);
                    db_exec('DELETE FROM shipping_zone_locations WHERE zone_id = ?', [$zid]);
                    foreach (['city' => post('cities'), 'province' => post('provinces')] as $type => $list) {
                        foreach (array_unique(array_filter(array_map(fn($x) => mb_substr(trim($x), 0, 100), preg_split('/[\n,]+/', (string)$list)))) as $name) {
                            $taken = db_one('SELECT z.name FROM shipping_zone_locations l JOIN shipping_zones z ON z.id = l.zone_id WHERE l.location_type = ? AND l.name = ?', [$type, $name]);
                            if ($taken) throw new InvalidArgumentException("“$name” already belongs to zone “{$taken['name']}”.");
                            db_exec('INSERT INTO shipping_zone_locations (zone_id, location_type, name) VALUES (?, ?, ?)', [$zid, $type, $name]);
                        }
                    }
                });
                flash('success', 'Zone saved.');
                break;
            case 'delete_zone':
                db_exec('DELETE FROM shipping_zones WHERE id = ?', [(int)post('id')]);
                flash('success', 'Zone deleted.');
                break;
            case 'save_rate':
                $rid = (int)post('id');
                $d = [(int)post('zone_id'), in_list(post('method'), ['standard', 'express'], 'standard'), mb_substr(post('label'), 0, 120) ?: 'Delivery', in_list(post('applies_to'), ['all', 'ready_to_ship', 'made_to_order'], 'all'),
                      to_money(post('rate')) ?? 0, to_money(post('free_over')), to_money(post('min_order')), clamp_int(post('est_days_min'), 0, 60), clamp_int(post('est_days_max'), 0, 90), post('status') === 'inactive' ? 'inactive' : 'active'];
                if ($d[7] > $d[8]) throw new InvalidArgumentException('Minimum days cannot exceed maximum days.');
                if ($rid) db_exec('UPDATE shipping_rates SET zone_id=?, method=?, label=?, applies_to=?, rate=?, free_over=?, min_order=?, est_days_min=?, est_days_max=?, status=? WHERE id = ?', array_merge($d, [$rid]));
                else db_insert('INSERT INTO shipping_rates (zone_id, method, label, applies_to, rate, free_over, min_order, est_days_min, est_days_max, status) VALUES (?,?,?,?,?,?,?,?,?,?)', $d);
                flash('success', 'Rate saved.');
                break;
            case 'delete_rate':
                db_exec('DELETE FROM shipping_rates WHERE id = ?', [(int)post('id')]);
                flash('success', 'Rate deleted.');
                break;
        }
        audit('shipping_' . $act, 'shipping', (int)post('id') ?: null);
    } catch (InvalidArgumentException $e) {
        flash('danger', $e->getMessage());
    }
    redirect(admin_url('shipping'));
}
$zones = db_all('SELECT * FROM shipping_zones ORDER BY sort_order, id');
$locs = [];
foreach (db_all('SELECT * FROM shipping_zone_locations ORDER BY name') as $l) $locs[(int)$l['zone_id']][$l['location_type']][] = $l['name'];
$rates = [];
foreach (db_all('SELECT * FROM shipping_rates ORDER BY method, applies_to') as $r) $rates[(int)$r['zone_id']][] = $r;
$zoneOpts = array_column($zones, 'name', 'id');
$admin_title = 'Shipping & delivery';
require __DIR__ . '/partials/header.php';
?>
<div class="alert alert-light border small">The customer's city is matched first, then their province; anything else uses the <strong>default zone</strong>. For each delivery method the most specific rate applies: a “made to order” rate when the bag has made-to-order/customised items, a “ready to ship” rate otherwise, else an “all items” rate. Production lead time is added to delivery estimates automatically. Also set the store-wide minimum order in Store settings → Checkout.</div>
<?php foreach ($zones as $z): $zid = (int)$z['id']; ?>
<div class="card mb-3"><div class="card-header d-flex align-items-center"><?= e($z['name']) ?> <?= $z['is_default'] ? '<span class="badge text-bg-primary ms-2">Default zone</span>' : '' ?> <?= status_badge($z['status']) ?>
  <form method="post" class="ms-auto" data-confirm="Delete this zone and its rates?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_zone"><input type="hidden" name="id" value="<?= $zid ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form></div>
  <div class="card-body"><div class="row g-4">
    <div class="col-lg-5"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save_zone"><input type="hidden" name="id" value="<?= $zid ?>">
      <?= f_text('name', 'Zone name', $z['name']) ?>
      <?= f_text('cities', 'Cities (one per line or comma separated)', implode("\n", $locs[$zid]['city'] ?? []), ['type' => 'textarea', 'rows' => 3]) ?>
      <?= f_text('provinces', 'Provinces / regions', implode("\n", $locs[$zid]['province'] ?? []), ['type' => 'textarea', 'rows' => 2]) ?>
      <div class="row"><div class="col-6"><?= f_toggle('cod_enabled', 'Cash on delivery available', $z['cod_enabled']) ?></div><div class="col-6"><?= f_toggle('is_default', 'Default zone (everywhere else)', $z['is_default']) ?></div>
      <div class="col-4"><?= f_text('cod_fee', 'COD fee', $z['cod_fee'], ['type' => 'number', 'step' => '0.01']) ?></div><div class="col-4"><?= f_text('cod_max_order', 'COD max order', $z['cod_max_order'], ['type' => 'number']) ?></div><div class="col-4"><?= f_select('status', 'Status', ['active' => 'Active', 'inactive' => 'Inactive'], $z['status']) ?></div></div>
      <input type="hidden" name="sort_order" value="<?= (int)$z['sort_order'] ?>"><button class="btn btn-sm btn-primary">Save zone</button></form></div>
    <div class="col-lg-7"><h6>Rates</h6>
      <?php foreach (array_merge($rates[$zid] ?? [], [['id' => 0, 'method' => 'standard', 'label' => '', 'applies_to' => 'all', 'rate' => '', 'free_over' => '', 'min_order' => '', 'est_days_min' => 2, 'est_days_max' => 5, 'status' => 'active']]) as $r): ?>
        <form method="post" class="row g-1 align-items-end mb-2 <?= $r['id'] ? '' : 'border-top pt-2' ?>"><?= csrf_field() ?><input type="hidden" name="action" value="save_rate"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="zone_id" value="<?= $zid ?>">
          <div class="col-6 col-md-3"><label class="small">Label</label><input class="form-control form-control-sm" name="label" value="<?= e($r['label']) ?>" placeholder="<?= $r['id'] ? '' : 'New rate…' ?>"></div>
          <div class="col-3 col-md-2"><label class="small">Method</label><select class="form-select form-select-sm" name="method"><option value="standard">Standard</option><option value="express"<?= $r['method'] === 'express' ? ' selected' : '' ?>>Express</option></select></div>
          <div class="col-3 col-md-2"><label class="small">Items</label><select class="form-select form-select-sm" name="applies_to"><?php foreach (['all' => 'All', 'ready_to_ship' => 'Ready to ship', 'made_to_order' => 'Made to order'] as $k => $l): ?><option value="<?= $k ?>"<?= $r['applies_to'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
          <div class="col-3 col-md-1"><label class="small">Rate</label><input class="form-control form-control-sm" name="rate" type="number" value="<?= e($r['rate']) ?>"></div>
          <div class="col-3 col-md-1"><label class="small">Free over</label><input class="form-control form-control-sm" name="free_over" type="number" value="<?= e($r['free_over']) ?>"></div>
          <div class="col-3 col-md-1"><label class="small">Min order</label><input class="form-control form-control-sm" name="min_order" type="number" value="<?= e($r['min_order']) ?>"></div>
          <div class="col-3 col-md-1"><label class="small">Days</label><div class="d-flex"><input class="form-control form-control-sm" name="est_days_min" type="number" value="<?= (int)$r['est_days_min'] ?>"><input class="form-control form-control-sm" name="est_days_max" type="number" value="<?= (int)$r['est_days_max'] ?>"></div></div>
          <div class="col-12 col-md-1 d-flex gap-1"><input type="hidden" name="status" value="active"><button class="btn btn-sm btn-primary" title="Save"><i class="bi bi-check2"></i></button>
          <?php if ($r['id']): ?><button class="btn btn-sm btn-light text-danger" name="action" value="delete_rate" title="Delete" onclick="return confirm('Delete rate?')"><i class="bi bi-trash"></i></button><?php endif; ?></div>
        </form>
      <?php endforeach; ?>
    </div>
  </div></div>
</div>
<?php endforeach; ?>
<div class="card"><div class="card-header">Add a zone</div><div class="card-body"><form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="action" value="save_zone"><input type="hidden" name="id" value="0"><input type="hidden" name="status" value="active"><input type="hidden" name="cod_enabled" value="1">
  <div class="col-md-4"><input class="form-control" name="name" placeholder="Zone name, e.g. Northern areas" required></div><div class="col-md-6"><input class="form-control" name="cities" placeholder="Cities, comma separated"></div><div class="col-md-2"><button class="btn btn-primary w-100">Add zone</button></div></form></div></div>
<?php require __DIR__ . '/partials/footer.php';
