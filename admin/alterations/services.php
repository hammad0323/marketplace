<?php
/** Alteration price list: estimated price range and turnaround per service, plus settings. */
require __DIR__ . '/../includes/init.php';
admin_require('alterations');
if (is_post()) {
    $a = input('action', '', 'post');
    if ($a === 'save') {
        foreach ((array) ($_POST['svc'] ?? []) as $sid => $s) {
            $min = max(0, (float) ($s['min'] ?? 0));
            db_update('alteration_services', [
                'category' => mb_substr(trim((string) ($s['category'] ?? 'General')), 0, 40) ?: 'General', 'garment' => mb_substr(trim((string) ($s['garment'] ?? '')), 0, 80) ?: 'Garment',
                'name' => mb_substr(trim((string) ($s['name'] ?? '')), 0, 120) ?: 'Service', 'description' => nullable(mb_substr(trim((string) ($s['description'] ?? '')), 0, 500)),
                'price_min' => $min, 'price_max' => max($min, (float) ($s['max'] ?? 0)), 'days_min' => max(0, (int) ($s['dmin'] ?? 0)),
                'days_max' => max((int) ($s['dmin'] ?? 0), (int) ($s['dmax'] ?? 0)), 'is_active' => !empty($s['active']) ? 1 : 0, 'sort_order' => (int) ($s['sort'] ?? 0),
            ], 'id = ?', [(int) $sid]);
        }
        flash('success', 'Price list saved.');
    } elseif ($a === 'add') {
        $min = max(0, (float) input_float('price_min', 0));
        db_insert('alteration_services', ['category' => mb_substr(input('category', 'General', 'post'), 0, 40) ?: 'General', 'garment' => mb_substr(input('garment', '', 'post'), 0, 80) ?: 'Garment',
            'name' => mb_substr(input('name', '', 'post'), 0, 120) ?: 'Service', 'price_min' => $min, 'price_max' => max($min, (float) input_float('price_max', 0)),
            'days_min' => max(0, input_int('days_min')), 'days_max' => max(input_int('days_min'), input_int('days_max')), 'sort_order' => 99]);
        flash('success', 'Service added.');
    } elseif ($a === 'delete') {
        db_exec('DELETE FROM alteration_services WHERE id = ?', [input_int('id')]);
        flash('success', 'Service deleted (past requests keep their copy).');
    } elseif ($a === 'settings') {
        setting_save('alteration_pickup_fee', (string) max(0, (float) input_float('alteration_pickup_fee', 0)), 'services');
        setting_save('alteration_dropoff_note', mb_substr(input('alteration_dropoff_note', '', 'post'), 0, 255), 'services');
        setting_save('alteration_notify_email', mb_substr(input('alteration_notify_email', '', 'post'), 0, 500), 'services');
        flash('success', 'Settings saved.');
    }
    audit_log('alteration_services_' . $a, 'alteration_service');
    redirect('admin/alterations/services.php');
}
$services = alteration_services(false);
$cats = array_values(array_unique(array_merge(['Men', 'Women', 'Bridal', 'General'], array_column($services, 'category'))));
admin_header('Alteration services & prices');
?>
<p class="text-muted">Customers see these estimated prices and times on the <a href="<?= e(url('alterations')) ?>" target="_blank">Alterations page</a>. You confirm the final price on each request after inspecting the garment.</p>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save">
<div class="card a-card"><div class="table-responsive"><table class="table table-sm a-table align-middle mb-0">
<thead><tr><th>Category</th><th>Garment</th><th>Service</th><th>Description</th><th>Price from–to</th><th>Days min–max</th><th>Order</th><th>On</th><th></th></tr></thead><tbody>
<?php foreach ($services as $s): $sid = (int) $s['id']; ?>
<tr><td><input class="form-control form-control-sm" style="width:90px" name="svc[<?= $sid ?>][category]" value="<?= e($s['category']) ?>" list="cats"></td>
<td><input class="form-control form-control-sm" style="width:140px" name="svc[<?= $sid ?>][garment]" value="<?= e($s['garment']) ?>"></td>
<td><input class="form-control form-control-sm" style="min-width:170px" name="svc[<?= $sid ?>][name]" value="<?= e($s['name']) ?>"></td>
<td><input class="form-control form-control-sm" style="min-width:160px" name="svc[<?= $sid ?>][description]" value="<?= e($s['description']) ?>"></td>
<td class="text-nowrap"><input class="form-control form-control-sm d-inline-block" style="width:80px" type="number" min="0" name="svc[<?= $sid ?>][min]" value="<?= e((float) $s['price_min']) ?>">–<input class="form-control form-control-sm d-inline-block" style="width:80px" type="number" min="0" name="svc[<?= $sid ?>][max]" value="<?= e((float) $s['price_max']) ?>"></td>
<td class="text-nowrap"><input class="form-control form-control-sm d-inline-block" style="width:52px" type="number" min="0" name="svc[<?= $sid ?>][dmin]" value="<?= (int) $s['days_min'] ?>">–<input class="form-control form-control-sm d-inline-block" style="width:52px" type="number" min="0" name="svc[<?= $sid ?>][dmax]" value="<?= (int) $s['days_max'] ?>"></td>
<td><input class="form-control form-control-sm" style="width:56px" type="number" name="svc[<?= $sid ?>][sort]" value="<?= (int) $s['sort_order'] ?>"></td>
<td><input class="form-check-input" type="checkbox" name="svc[<?= $sid ?>][active]" value="1"<?= (int) $s['is_active'] ? ' checked' : '' ?>></td>
<td><button class="btn btn-sm btn-light text-danger" form="del<?= $sid ?>"><i class="bi bi-trash"></i></button></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
<datalist id="cats"><?php foreach ($cats as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
<button class="btn btn-dark">Save price list</button></form>
<?php foreach ($services as $s): ?><form id="del<?= (int) $s['id'] ?>" method="post" class="d-none" data-confirm="Delete “<?= e($s['garment'] . ' — ' . $s['name']) ?>”?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"></form><?php endforeach; ?>
<div class="row g-4 mt-1"><div class="col-lg-7"><div class="card a-card"><div class="card-header">Add a service</div><div class="card-body">
<form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="action" value="add">
    <div class="col-md-3"><input class="form-control" name="category" placeholder="Category" list="cats" required></div>
    <div class="col-md-4"><input class="form-control" name="garment" placeholder="Garment, e.g. Prince Coat" required></div>
    <div class="col-md-5"><input class="form-control" name="name" placeholder="Service, e.g. Full fitting" required></div>
    <div class="col-md-3"><input class="form-control" type="number" min="0" name="price_min" placeholder="Price from"></div>
    <div class="col-md-3"><input class="form-control" type="number" min="0" name="price_max" placeholder="Price to"></div>
    <div class="col-md-2"><input class="form-control" type="number" min="0" name="days_min" placeholder="Days min"></div>
    <div class="col-md-2"><input class="form-control" type="number" min="0" name="days_max" placeholder="Days max"></div>
    <div class="col-md-2"><button class="btn btn-dark w-100">Add</button></div>
</form></div></div></div>
<div class="col-lg-5"><div class="card a-card"><div class="card-header">Settings</div><div class="card-body">
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
    <?= a_input('alteration_pickup_fee', 'Courier pickup & return fee (0 = free)', setting('alteration_pickup_fee', 0), 'number', ['min' => 0]) ?>
    <?= a_input('alteration_dropoff_note', 'Drop-off location / hours (shown to customers)', setting('alteration_dropoff_note')) ?>
    <?= a_input('alteration_notify_email', 'Notify these emails of new requests (comma separated)', setting('alteration_notify_email')) ?>
    <button class="btn btn-dark w-100">Save settings</button>
</form></div></div></div></div>
<?php admin_footer();
