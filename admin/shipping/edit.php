<?php
require __DIR__ . '/../includes/init.php';
admin_require('shipping');
$id = input_int('id');
$zone = $id ? db_row('SELECT * FROM shipping_zones WHERE id = ?', [$id]) : null;
if (is_post()) {
    $d = ['name' => mb_substr(input('name', '', 'post'), 0, 120), 'is_default' => input_bool('is_default'), 'cod_enabled' => input_bool('cod_enabled'),
          'is_active' => input_bool('is_active'), 'sort_order' => input_int('sort_order')];
    if (mb_strlen($d['name']) < 2) {
        flash('error', 'Zone name is required.');
        redirect('admin/shipping/edit.php' . ($id ? '?id=' . $id : ''));
    }
    db_transaction(function () use (&$id, $d) {
        if ($d['is_default']) db_exec('UPDATE shipping_zones SET is_default = 0');
        $id ? db_update('shipping_zones', $d, 'id = ?', [$id]) : ($id = db_insert('shipping_zones', $d));
        db_exec('DELETE FROM shipping_zone_locations WHERE zone_id = ?', [$id]);
        foreach (['city' => 'cities', 'region' => 'regions'] as $type => $field) {
            foreach (array_unique(array_filter(array_map('trim', preg_split('/[\n,]+/', (string) ($_POST[$field] ?? ''))))) as $n) {
                db_insert('shipping_zone_locations', ['zone_id' => $id, 'location_type' => $type, 'name' => mb_substr($n, 0, 100)]);
            }
        }
        foreach (['standard', 'express'] as $m) {
            $r = $_POST['rate'][$m] ?? [];
            $enabled = !empty($r['active']);
            $exists = db_val('SELECT id FROM shipping_rates WHERE zone_id = ? AND method = ?', [$id, $m]);
            if (!$enabled && !$exists) continue;
            $row = ['zone_id' => $id, 'method' => $m, 'label' => mb_substr(trim((string) ($r['label'] ?? '')), 0, 80) ?: ($m === 'express' ? 'Express Delivery' : 'Standard Delivery'),
                'fee' => max(0, (float) ($r['fee'] ?? 0)), 'free_above' => is_numeric($r['free_above'] ?? null) ? (float) $r['free_above'] : null,
                'min_order' => is_numeric($r['min_order'] ?? null) ? (float) $r['min_order'] : null, 'days_min' => max(0, (int) ($r['days_min'] ?? 2)),
                'days_max' => max((int) ($r['days_min'] ?? 2), (int) ($r['days_max'] ?? 5)), 'is_active' => $enabled ? 1 : 0];
            $exists ? db_update('shipping_rates', $row, 'id = ?', [(int) $exists]) : db_insert('shipping_rates', $row);
        }
    });
    if (!db_val('SELECT 1 FROM shipping_zones WHERE is_default = 1')) {
        flash('warning', 'No default zone is set — customers in unlisted cities will get the fallback fee.');
    }
    audit_log('shipping_zone_saved', 'shipping_zone', $id, $d);
    flash('success', 'Zone saved.');
    redirect('admin/shipping/');
}
$z = $zone ?? ['is_active' => 1, 'cod_enabled' => 1, 'sort_order' => 0];
$cities = $id ? db_col("SELECT name FROM shipping_zone_locations WHERE zone_id = ? AND location_type = 'city' ORDER BY name", [$id]) : [];
$regions = $id ? db_col("SELECT name FROM shipping_zone_locations WHERE zone_id = ? AND location_type = 'region' ORDER BY name", [$id]) : [];
$rates = $id ? array_column(db_all('SELECT * FROM shipping_rates WHERE zone_id = ?', [$id]), null, 'method') : [];
admin_header($id ? 'Edit delivery zone' : 'Add delivery zone');
?>
<form method="post"><?= csrf_field() ?>
<div class="row g-4"><div class="col-lg-6"><div class="card a-card"><div class="card-body">
    <?= a_input('name', 'Zone name', $z['name'] ?? '', 'text', ['required' => true]) ?>
    <?= a_textarea('cities', 'Cities (one per line or comma separated)', implode("\n", $cities), 5) ?>
    <?= a_textarea('regions', 'Provinces / regions', implode("\n", $regions), 3, [], 'Used when the city is not listed in any zone.') ?>
    <?= a_check('is_default', 'Default zone (everywhere else)', (int) ($z['is_default'] ?? 0)) ?>
    <?= a_check('cod_enabled', 'Cash on Delivery available in this zone', (int) $z['cod_enabled']) ?>
    <?= a_check('is_active', 'Active', (int) $z['is_active']) ?>
    <?= a_input('sort_order', 'Priority (lower first)', $z['sort_order'], 'number') ?>
</div></div></div>
<div class="col-lg-6">
<?php foreach (['standard' => 'Standard delivery', 'express' => 'Express delivery'] as $m => $title): $r = $rates[$m] ?? ['is_active' => $m === 'standard' ? 1 : 0, 'label' => $title, 'fee' => '', 'days_min' => $m === 'express' ? 1 : 2, 'days_max' => $m === 'express' ? 2 : 5]; ?>
<div class="card a-card"><div class="card-header"><?= $title ?></div><div class="card-body">
    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="rate[<?= $m ?>][active]" value="1" id="ra<?= $m ?>"<?= (int) $r['is_active'] ? ' checked' : '' ?>><label class="form-check-label" for="ra<?= $m ?>">Offer <?= strtolower($title) ?></label></div>
    <div class="row g-2">
        <div class="col-12"><label class="form-label small">Label</label><input class="form-control" name="rate[<?= $m ?>][label]" value="<?= e($r['label']) ?>"></div>
        <div class="col-4"><label class="form-label small">Fee</label><input class="form-control" type="number" step="1" min="0" name="rate[<?= $m ?>][fee]" value="<?= e($r['fee']) ?>"></div>
        <div class="col-4"><label class="form-label small">Free above</label><input class="form-control" type="number" step="1" name="rate[<?= $m ?>][free_above]" value="<?= e($r['free_above'] ?? '') ?>"></div>
        <div class="col-4"><label class="form-label small">Min order</label><input class="form-control" type="number" step="1" name="rate[<?= $m ?>][min_order]" value="<?= e($r['min_order'] ?? '') ?>"></div>
        <div class="col-6"><label class="form-label small">Days (min)</label><input class="form-control" type="number" min="0" name="rate[<?= $m ?>][days_min]" value="<?= (int) $r['days_min'] ?>"></div>
        <div class="col-6"><label class="form-label small">Days (max)</label><input class="form-control" type="number" min="0" name="rate[<?= $m ?>][days_max]" value="<?= (int) $r['days_max'] ?>"></div>
    </div>
</div></div>
<?php endforeach; ?>
</div></div>
<?= a_save_bar('admin/shipping/', 'Save zone') ?>
</form>
<?php admin_footer();
