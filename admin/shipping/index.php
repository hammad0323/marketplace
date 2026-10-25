<?php
/** Delivery zones (by city / province), rates, free-delivery thresholds and COD availability. */
require __DIR__ . '/../includes/init.php';
admin_require('shipping');
if (is_post()) {
    $a = input('action', '', 'post');
    if ($a === 'settings') {
        foreach (['free_shipping_threshold', 'default_shipping_fee', 'min_order_value'] as $k) {
            setting_save($k, (string) (float) input_float($k, 0), 'orders');
        }
        setting_save('express_enabled', (string) input_bool('express_enabled'), 'orders');
        setting_save('delivery_note', mb_substr(input('delivery_note', '', 'post'), 0, 500), 'orders');
        setting_save('checkout_regions', mb_substr(input('checkout_regions', '', 'post'), 0, 2000), 'orders');
        audit_log('shipping_settings', 'settings');
        flash('success', 'Delivery settings saved.');
    } elseif ($a === 'urgent') {
        setting_save('urgent_enabled', (string) input_bool('urgent_enabled'), 'services');
        foreach ((array) ($_POST['urg'] ?? []) as $uid => $u) {
            if (!empty($u['delete'])) {
                db_exec('DELETE FROM urgent_delivery_rates WHERE id = ?', [(int) $uid]);
                continue;
            }
            $cut = preg_match('/^\d{2}:\d{2}$/', (string) ($u['cutoff'] ?? '')) ? $u['cutoff'] . ':00' : null;
            db_update('urgent_delivery_rates', ['city' => mb_substr(trim((string) $u['city']), 0, 100) ?: 'City', 'fee' => max(0, (float) ($u['fee'] ?? 0)),
                'label' => mb_substr(trim((string) ($u['label'] ?? '')), 0, 80) ?: 'Urgent Delivery', 'days_min' => max(0, (int) ($u['dmin'] ?? 0)),
                'days_max' => max((int) ($u['dmin'] ?? 0), (int) ($u['dmax'] ?? 1)), 'cutoff_time' => $cut, 'is_active' => !empty($u['active']) ? 1 : 0], 'id = ?', [(int) $uid]);
        }
        $newCity = trim(input('new_city', '', 'post'));
        if ($newCity !== '') {
            db_exec('INSERT INTO urgent_delivery_rates (city, fee, label, days_min, days_max, cutoff_time) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE fee = VALUES(fee), is_active = 1',
                [mb_substr($newCity, 0, 100), max(0, (float) input_float('new_fee', 0)), mb_substr(input('new_label', 'Urgent Delivery', 'post'), 0, 80) ?: 'Urgent Delivery',
                 max(0, input_int('new_dmin')), max(input_int('new_dmin'), input_int('new_dmax', 1)), preg_match('/^\d{2}:\d{2}$/', input('new_cutoff', '', 'post')) ? input('new_cutoff', '', 'post') . ':00' : null]);
        }
        audit_log('urgent_rates_saved', 'settings');
        flash('success', 'Urgent delivery rates saved.');
    } elseif ($a === 'delete') {
        $z = db_row('SELECT * FROM shipping_zones WHERE id = ?', [input_int('id')]);
        if ($z && (int) $z['is_default']) {
            flash('error', 'The default zone cannot be deleted — make another zone the default first.');
        } elseif ($z) {
            db_exec('DELETE FROM shipping_zones WHERE id = ?', [(int) $z['id']]);
            audit_log('shipping_zone_deleted', 'shipping_zone', (int) $z['id'], ['name' => $z['name']]);
            flash('success', 'Zone deleted.');
        }
    }
    redirect('admin/shipping/');
}
$zones = db_all('SELECT * FROM shipping_zones ORDER BY sort_order, id');
admin_header('Delivery & shipping');
?>
<div class="row g-4"><div class="col-xl-8">
<div class="d-flex justify-content-between mb-3"><p class="text-muted mb-0">Orders are matched to a zone by <b>city</b> first, then <b>province / region</b>, then the <b>default zone</b>.</p><a class="btn btn-dark" href="<?= e(url('admin/shipping/edit.php')) ?>"><i class="bi bi-plus-lg"></i> Add zone</a></div>
<?php foreach ($zones as $z):
    $locs = db_all('SELECT * FROM shipping_zone_locations WHERE zone_id = ? ORDER BY location_type, name', [(int) $z['id']]);
    $rates = db_all('SELECT * FROM shipping_rates WHERE zone_id = ? ORDER BY method', [(int) $z['id']]); ?>
<div class="card a-card"><div class="card-header d-flex align-items-center gap-2"><?= e($z['name']) ?> <?= (int) $z['is_default'] ? a_badge('Default', 'dark') : '' ?> <?= (int) $z['cod_enabled'] ? a_badge('COD available', 'success') : a_badge('No COD', 'warning') ?> <?= (int) $z['is_active'] ? '' : a_badge('Inactive', 'secondary') ?>
    <span class="ms-auto"><a class="btn btn-sm btn-light" href="<?= e(url('admin/shipping/edit.php?id=' . $z['id'])) ?>"><i class="bi bi-pencil"></i></a> <?= a_post_button(url('admin/shipping/'), ['action' => 'delete', 'id' => $z['id']], '<i class="bi bi-trash"></i>', 'btn btn-sm btn-light text-danger', 'Delete zone ' . $z['name'] . '?') ?></span></div>
    <div class="card-body small">
        <p class="mb-2"><strong>Covers:</strong> <?= $locs ? e(implode(', ', array_map(fn($l) => $l['name'] . ($l['location_type'] === 'region' ? ' (province)' : ''), $locs))) : ((int) $z['is_default'] ? 'Everywhere not covered by another zone' : '—') ?></p>
        <table class="table table-sm mb-0"><thead><tr><th>Method</th><th>Fee</th><th>Free above</th><th>Min order</th><th>Delivery time</th></tr></thead><tbody>
        <?php foreach ($rates as $r): ?><tr class="<?= (int) $r['is_active'] ? '' : 'text-muted' ?>"><td><?= e($r['label']) ?></td><td><?= e(money($r['fee'])) ?></td><td><?= $r['free_above'] !== null ? e(money($r['free_above'])) : '—' ?></td><td><?= $r['min_order'] !== null ? e(money($r['min_order'])) : '—' ?></td><td><?= (int) $r['days_min'] ?>–<?= (int) $r['days_max'] ?> working days</td></tr><?php endforeach; ?>
        </tbody></table>
    </div></div>
<?php endforeach; ?>
<?php $urgent = db_all('SELECT * FROM urgent_delivery_rates ORDER BY sort_order, city'); ?>
<div class="card a-card" id="urgent"><div class="card-header d-flex align-items-center gap-2"><i class="bi bi-lightning-charge-fill text-warning"></i> Urgent delivery — city-wise prices</div><div class="card-body">
    <p class="small text-muted">Shown on product pages and as a delivery option at checkout when the customer's city is listed. Not offered for custom-made / made-to-order items, or products with urgent delivery switched off. The free-delivery threshold does not apply to urgent delivery.</p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="urgent">
    <?= a_check('urgent_enabled', 'Offer urgent delivery', setting_bool('urgent_enabled', true)) ?>
    <div class="table-responsive"><table class="table table-sm a-table align-middle"><thead><tr><th>City</th><th>Fee</th><th>Label</th><th>Days (0 = same day)</th><th>Order cut-off</th><th>On</th><th>Delete</th></tr></thead><tbody>
    <?php foreach ($urgent as $u): $uid = (int) $u['id']; ?>
        <tr><td><input class="form-control form-control-sm" name="urg[<?= $uid ?>][city]" value="<?= e($u['city']) ?>"></td>
        <td><input class="form-control form-control-sm" style="width:90px" type="number" min="0" name="urg[<?= $uid ?>][fee]" value="<?= e((float) $u['fee']) ?>"></td>
        <td><input class="form-control form-control-sm" name="urg[<?= $uid ?>][label]" value="<?= e($u['label']) ?>"></td>
        <td class="text-nowrap"><input class="form-control form-control-sm d-inline-block" style="width:52px" type="number" min="0" name="urg[<?= $uid ?>][dmin]" value="<?= (int) $u['days_min'] ?>">–<input class="form-control form-control-sm d-inline-block" style="width:52px" type="number" min="0" name="urg[<?= $uid ?>][dmax]" value="<?= (int) $u['days_max'] ?>"></td>
        <td><input class="form-control form-control-sm" type="time" name="urg[<?= $uid ?>][cutoff]" value="<?= e($u['cutoff_time'] ? substr($u['cutoff_time'], 0, 5) : '') ?>"></td>
        <td><input class="form-check-input" type="checkbox" name="urg[<?= $uid ?>][active]" value="1"<?= (int) $u['is_active'] ? ' checked' : '' ?>></td>
        <td><input class="form-check-input" type="checkbox" name="urg[<?= $uid ?>][delete]" value="1"></td></tr>
    <?php endforeach; ?>
        <tr class="table-light"><td><input class="form-control form-control-sm" name="new_city" placeholder="Add city…" list="zoneCities"></td><td><input class="form-control form-control-sm" style="width:90px" type="number" min="0" name="new_fee" placeholder="Fee"></td>
        <td><input class="form-control form-control-sm" name="new_label" placeholder="e.g. Same-day delivery"></td>
        <td class="text-nowrap"><input class="form-control form-control-sm d-inline-block" style="width:52px" type="number" min="0" name="new_dmin" value="0">–<input class="form-control form-control-sm d-inline-block" style="width:52px" type="number" min="0" name="new_dmax" value="1"></td>
        <td><input class="form-control form-control-sm" type="time" name="new_cutoff" value="14:00"></td><td colspan="2"></td></tr>
    </tbody></table></div>
    <datalist id="zoneCities"><?php foreach (db_col("SELECT DISTINCT name FROM shipping_zone_locations WHERE location_type = 'city' ORDER BY name") as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
    <button class="btn btn-dark">Save urgent delivery</button>
    </form>
</div></div>
</div><div class="col-xl-4">
<div class="card a-card"><div class="card-header">General delivery settings</div><div class="card-body">
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
    <?= a_input('free_shipping_threshold', 'Free standard delivery above (all zones, 0 = off)', setting('free_shipping_threshold', 0), 'number', ['step' => '1', 'min' => 0]) ?>
    <?= a_input('default_shipping_fee', 'Fallback delivery fee (used only if no zone matches)', setting('default_shipping_fee', 300), 'number', ['step' => '1', 'min' => 0]) ?>
    <?= a_input('min_order_value', 'Minimum order value (0 = none)', setting('min_order_value', 0), 'number', ['step' => '1', 'min' => 0]) ?>
    <?= a_check('express_enabled', 'Offer express delivery where a zone has an express rate', setting_bool('express_enabled', true)) ?>
    <?= a_textarea('delivery_note', 'Dispatch note (product pages)', setting('delivery_note'), 2) ?>
    <?= a_textarea('checkout_regions', 'Provinces / regions at checkout (one per line, blank = Pakistan defaults)', setting('checkout_regions'), 4) ?>
    <button class="btn btn-dark w-100">Save settings</button>
</form>
<p class="small text-muted mt-3 mb-0">Production lead time of made-to-order items is added to delivery estimates automatically. COD fee is configured under Payment Methods.</p>
</div></div>
</div></div>
<?php admin_footer();
