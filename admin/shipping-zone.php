<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('shipping.manage');
$id = input_int('id', 0, 'get');
$zone = $id ? db_one('SELECT * FROM shipping_zones WHERE id = ?', [$id]) : null;
$errors = [];
if (is_post()) {
    require_csrf();
    $d = ['name' => mb_substr(input('name'), 0, 120), 'is_default' => input_bool('is_default'), 'cod_available' => input_bool('cod_available'), 'is_active' => input_bool('is_active'), 'sort_order' => input_int('sort_order')];
    if ($d['name'] === '') {
        $errors[] = 'Zone name is required.';
    }
    $rates = [];
    foreach (['standard', 'express'] as $m) {
        $r = input_array('rate_' . $m);
        if (!empty($r['enabled'])) {
            $min = max(0, (int) ($r['min_days'] ?? 1));
            $max = max($min, (int) ($r['max_days'] ?? $min));
            $rates[$m] = ['name' => mb_substr(trim($r['name'] ?? '') ?: ucfirst($m) . ' delivery', 0, 120), 'rate' => max(0, round((float) ($r['rate'] ?? 0), 2)),
                'free_over' => is_numeric($r['free_over'] ?? '') ? max(0, round((float) $r['free_over'], 2)) : null, 'min_days' => $min, 'max_days' => $max, 'is_active' => 1];
        }
    }
    if (!isset($rates['standard'])) {
        $errors[] = 'A zone needs a standard delivery rate.';
    }
    $locations = [];
    foreach (preg_split('/\R/', input('cities')) as $line) {
        $line = trim($line);
        if ($line !== '') {
            $locations[] = ['city' => mb_substr($line, 0, 100), 'region' => null];
        }
    }
    foreach (input_array('regions') as $reg) {
        if (in_array($reg, pk_regions(), true)) {
            $locations[] = ['city' => null, 'region' => $reg];
        }
    }
    if (!$errors) {
        db_tx(function () use (&$id, $zone, $d, $rates, $locations) {
            if ($d['is_default']) {
                db_exec('UPDATE shipping_zones SET is_default = 0');
            }
            $zone ? db_update('shipping_zones', $d, 'id = ?', [$id]) : ($id = db_insert('shipping_zones', $d));
            db_exec('DELETE FROM shipping_zone_locations WHERE zone_id = ?', [$id]);
            foreach ($locations as $l) {
                db_insert('shipping_zone_locations', $l + ['zone_id' => $id]);
            }
            db_exec('DELETE FROM shipping_rates WHERE zone_id = ?', [$id]);
            foreach ($rates as $m => $r) {
                db_insert('shipping_rates', $r + ['zone_id' => $id, 'method' => $m]);
            }
        });
        audit_log('shipping_zone_saved', 'shipping_zone', $id, ['name' => $d['name']]);
        flash('success', 'Zone saved.');
        redirect(admin_url('shipping'));
    }
}
$z = $zone ?? ['is_default' => 0, 'cod_available' => 1, 'is_active' => 1, 'sort_order' => 0];
$cities = $id ? db_col("SELECT city FROM shipping_zone_locations WHERE zone_id = ? AND city IS NOT NULL AND city <> ''", [$id]) : [];
$regions = $id ? db_col("SELECT region FROM shipping_zone_locations WHERE zone_id = ? AND (city IS NULL OR city = '')", [$id]) : [];
$rateRows = [];
foreach ($id ? db_all('SELECT * FROM shipping_rates WHERE zone_id = ?', [$id]) : [] as $r) {
    $rateRows[$r['method']] = $r;
}
admin_header($id ? 'Edit delivery zone' : 'Add delivery zone', 'shipping');
?>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<form method="post"><?= csrf_field() ?>
<div class="row g-3">
  <div class="col-lg-5"><div class="card"><div class="card-body">
    <?= f_text('name', 'Zone name', $z['name'] ?? '', ['required' => true]) ?>
    <?= f_textarea('cities', 'Cities (one per line)', implode("\n", $cities), ['rows' => 5], 'Matched case-insensitively against the city entered at checkout.') ?>
    <label class="form-label">Whole provinces / regions</label>
    <?php foreach (pk_regions() as $reg): ?><label class="form-check"><input class="form-check-input" type="checkbox" name="regions[]" value="<?= e($reg) ?>" <?= in_array($reg, $regions, true) ? 'checked' : '' ?>> <?= e($reg) ?></label><?php endforeach; ?>
    <hr>
    <?= f_check('is_default', 'Default zone (applies to all unmatched locations)', (int) $z['is_default']) ?>
    <?= f_check('cod_available', 'Cash on delivery available in this zone', (int) $z['cod_available']) ?>
    <?= f_check('is_active', 'Active', (int) $z['is_active']) ?>
    <?= f_number('sort_order', 'Priority (lower first)', $z['sort_order'], ['step' => 1]) ?>
  </div></div></div>
  <div class="col-lg-7">
    <?php foreach (['standard' => 'Standard delivery', 'express' => 'Express delivery (optional)'] as $m => $title): $r = $rateRows[$m] ?? null; ?>
      <div class="card mb-3"><div class="card-header"><?= e($title) ?></div><div class="card-body"><div class="row">
        <div class="col-12"><label class="form-check form-switch mb-3"><input type="checkbox" class="form-check-input" name="rate_<?= $m ?>[enabled]" value="1" <?= $r || ($m === 'standard' && !$id) ? 'checked' : '' ?>> Offer <?= $m ?> delivery</label></div>
        <div class="col-md-6"><?= f_text('rate_' . $m . '[name]', 'Label at checkout', $r['name'] ?? ($m === 'standard' ? 'Standard delivery' : 'Express delivery')) ?></div>
        <div class="col-md-6"><?= f_number('rate_' . $m . '[rate]', 'Charge', $r['rate'] ?? '', ['min' => 0]) ?></div>
        <div class="col-md-4"><?= f_number('rate_' . $m . '[free_over]', 'Free over (optional)', $r['free_over'] ?? '', ['min' => 0], $m === 'standard' ? 'Overrides the global threshold.' : 'Express is never free automatically.') ?></div>
        <div class="col-md-4"><?= f_number('rate_' . $m . '[min_days]', 'Min days', $r['min_days'] ?? 2, ['min' => 0, 'step' => 1]) ?></div>
        <div class="col-md-4"><?= f_number('rate_' . $m . '[max_days]', 'Max days', $r['max_days'] ?? 5, ['min' => 0, 'step' => 1]) ?></div>
      </div></div></div>
    <?php endforeach; ?>
  </div>
</div>
<div class="sticky-actions"><?= f_submit() ?> <a class="btn btn-light" href="<?= e(admin_url('shipping')) ?>">Back</a></div>
</form>
<?php admin_footer();
