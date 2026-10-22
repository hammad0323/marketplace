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
