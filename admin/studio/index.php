<?php
/** Custom Size Studio settings: garments, charges, production time, size charts, size finder and style options. */
require __DIR__ . '/../includes/init.php';
admin_require('custom_studio');
$garments = studio_garments(false);
$gid = input_int('g') ?: (int) ($garments[0]['id'] ?? 0);
$garment = $gid ? studio_garment($gid, false) : null;
$drawable = studio_drawable_options();

if (is_post() && $garment) {
    $a = input('action', '', 'post');
    if ($a === 'garment') {
        db_update('custom_garments', [
            'name' => mb_substr(input('name', $garment['name'], 'post'), 0, 120), 'intro' => nullable(mb_substr(input('intro', '', 'post'), 0, 500)),
            'fee' => max(0, (float) input_float('fee', 0)), 'production_days_min' => max(0, input_int('production_days_min', 4)),
            'production_days_max' => max(input_int('production_days_min', 4), input_int('production_days_max', 7)), 'is_active' => input_bool('is_active'), 'sort_order' => input_int('sort_order'),
        ], 'id = ?', [$gid]);
    } elseif ($a === 'chart') {
        db_transaction(function () use ($gid, $garment) {
            db_exec('DELETE FROM custom_size_chart WHERE garment_id = ?', [$gid]);
            $sizes = array_values(array_unique(array_filter(array_map(fn($x) => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $x)), (array) ($_POST['sizes'] ?? [])))));
            foreach ($sizes as $si => $sz) {
                foreach (array_keys($garment['def']['measurements']) as $k) {
                    $v = $_POST['chart'][$si][$k] ?? '';
                    if (is_numeric($v)) {
                        db_insert('custom_size_chart', ['garment_id' => $gid, 'size_label' => $sz, 'measure_key' => $k, 'value' => round((float) $v, 1)]);
                    }
                }
                $f = $_POST['fit'][$si] ?? [];
                db_exec('DELETE FROM custom_size_fit WHERE garment_id = ? AND size_label = ?', [$gid, $sz]);
                if (is_numeric($f['hmin'] ?? null) && is_numeric($f['hmax'] ?? null) && is_numeric($f['wmin'] ?? null) && is_numeric($f['wmax'] ?? null)) {
                    db_insert('custom_size_fit', ['garment_id' => $gid, 'size_label' => $sz, 'height_min_in' => (float) $f['hmin'], 'height_max_in' => (float) $f['hmax'],
                        'weight_min_kg' => (float) $f['wmin'], 'weight_max_kg' => (float) $f['wmax'], 'sort_order' => $si]);
                }
            }
            db_exec('DELETE FROM custom_size_fit WHERE garment_id = ?' . ($sizes ? ' AND size_label NOT IN (' . db_in($sizes) . ')' : ''), array_merge([$gid], $sizes));
        });
    } elseif ($a === 'options') {
        foreach ((array) ($_POST['opt'] ?? []) as $oid => $o) {
            db_update('custom_style_options', ['label' => mb_substr(trim((string) ($o['label'] ?? '')), 0, 80) ?: 'Option', 'extra_price' => max(0, (float) ($o['price'] ?? 0)),
                'is_active' => !empty($o['active']) ? 1 : 0, 'sort_order' => (int) ($o['sort'] ?? 0)], 'id = ? AND garment_id = ?', [(int) $oid, $gid]);
        }
        foreach ((array) ($_POST['default'] ?? []) as $group => $oid) {
            db_exec('UPDATE custom_style_options SET is_default = (id = ?) WHERE garment_id = ? AND option_group = ?', [(int) $oid, $gid, (string) $group]);
        }
        foreach ((array) ($_POST['delete'] ?? []) as $oid) {
            db_exec('DELETE FROM custom_style_options WHERE id = ? AND garment_id = ?', [(int) $oid, $gid]);
        }
    } elseif ($a === 'add_option') {
        $group = input('option_group', '', 'post');
        $key = input('value_key', '', 'post');
        if (isset($garment['def']['groups'][$group], $drawable[$group][$key])) {
            db_exec('INSERT INTO custom_style_options (garment_id, option_group, value_key, label, extra_price, sort_order) VALUES (?, ?, ?, ?, ?, 99)
                ON DUPLICATE KEY UPDATE is_active = 1, label = VALUES(label), extra_price = VALUES(extra_price)',
                [$gid, $group, $key, mb_substr(input('label', '', 'post'), 0, 80) ?: $drawable[$group][$key], max(0, (float) input_float('extra_price', 0))]);
        } else {
            flash('error', 'Choose a style group and a matching style.');
        }
    }
    audit_log('studio_' . $a, 'custom_garment', $gid);
    flash('success', 'Saved.');
    redirect('admin/studio/?g=' . $gid);
}
$chart = $garment ? studio_chart($gid) : [];
$fit = $garment ? array_column(studio_fit_table($gid), null, 'size_label') : [];
$opts = $garment ? studio_options($gid, false) : [];
$productCount = $garment ? (int) db_val('SELECT COUNT(*) FROM products WHERE custom_garment_id = ?', [$gid]) : 0;
admin_header('Custom Size Studio');
?>
<div class="a-help mb-3">Customers open the studio from any product linked to a garment type (Products » edit » <b>Customisation</b> tab). They choose colour and styles on a live mannequin, start from a standard size, then enter their measurements. Orders show the full measurement sheet for your tailors.</div>
<ul class="nav a-tabs"><?php foreach ($garments as $g): ?><li class="nav-item"><a class="nav-link<?= (int) $g['id'] === $gid ? ' active' : '' ?>" href="?g=<?= (int) $g['id'] ?>"><?= e($g['name']) ?><?= (int) $g['is_active'] ? '' : ' (off)' ?></a></li><?php endforeach; ?></ul>
<?php if ($garment): $def = $garment['def']; ?>
<div class="row g-4">
<div class="col-xl-4">
    <div class="card a-card"><div class="card-header">Garment settings</div><div class="card-body">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="garment">
            <?= a_input('name', 'Name', $garment['name']) ?>
            <?= a_textarea('intro', 'Intro (custom size page)', $garment['intro'], 2) ?>
            <?= a_input('fee', 'Extra charge for custom size (0 = ready-made price)', $garment['fee'], 'number', ['min' => 0, 'step' => '1']) ?>
            <div class="row"><div class="col-6"><?= a_input('production_days_min', 'Production days (min)', $garment['production_days_min'], 'number', ['min' => 0]) ?></div>
            <div class="col-6"><?= a_input('production_days_max', 'Production days (max)', $garment['production_days_max'], 'number', ['min' => 0]) ?></div></div>
            <?= a_input('sort_order', 'Order', $garment['sort_order'], 'number') ?>
            <?= a_check('is_active', 'Offer this garment in the studio', (int) $garment['is_active']) ?>
            <button class="btn btn-dark w-100">Save</button>
        </form>
        <p class="small text-muted mt-3 mb-0"><?= $productCount ?> product(s) use this garment. <a href="<?= e(url('admin/products/')) ?>">Link products</a></p>
    </div></div>
</div>
<div class="col-xl-8">
    <div class="card a-card"><div class="card-header">Standard size chart &amp; size finder <span class="small text-muted fw-normal">(body measurements, inches)</span></div><div class="card-body">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="chart">
        <div class="table-responsive"><table class="table table-sm a-table align-middle">
            <thead><tr><th>Size</th><?php foreach ($def['measurements'] as $k => $f): ?><th class="small"><?= e($f['label']) ?></th><?php endforeach; ?><th class="small">Height (in) min–max</th><th class="small">Weight (kg) min–max</th></tr></thead>
            <tbody>
            <?php $rows = array_keys($chart); $rows[] = ''; foreach ($rows as $si => $sz): $fr = $fit[$sz] ?? null; ?>
                <tr><td><input class="form-control form-control-sm" style="width:64px" name="sizes[<?= $si ?>]" value="<?= e($sz) ?>" placeholder="<?= $sz === '' ? 'Add' : '' ?>"></td>
                <?php foreach ($def['measurements'] as $k => $f): ?><td><input class="form-control form-control-sm" style="width:64px" type="number" step="0.5" min="<?= e($f['min']) ?>" max="<?= e($f['max']) ?>" name="chart[<?= $si ?>][<?= e($k) ?>]" value="<?= e($chart[$sz][$k] ?? '') ?>"></td><?php endforeach; ?>
                <td class="text-nowrap"><input class="form-control form-control-sm d-inline-block" style="width:58px" type="number" step="0.5" name="fit[<?= $si ?>][hmin]" value="<?= e($fr['height_min_in'] ?? '') ?>">–<input class="form-control form-control-sm d-inline-block" style="width:58px" type="number" step="0.5" name="fit[<?= $si ?>][hmax]" value="<?= e($fr['height_max_in'] ?? '') ?>"></td>
                <td class="text-nowrap"><input class="form-control form-control-sm d-inline-block" style="width:58px" type="number" name="fit[<?= $si ?>][wmin]" value="<?= e($fr['weight_min_kg'] ?? '') ?>">–<input class="form-control form-control-sm d-inline-block" style="width:58px" type="number" name="fit[<?= $si ?>][wmax]" value="<?= e($fr['weight_max_kg'] ?? '') ?>"></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <p class="small text-muted">Clear a size label to remove that size. Height/weight ranges power the “Find my size” helper (lengths are scaled to the customer's height automatically).</p>
        <button class="btn btn-dark">Save size chart</button>
        </form>
    </div></div>

    <div class="card a-card"><div class="card-header">Style options &amp; prices</div><div class="card-body">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="options">
        <?php foreach ($def['groups'] as $group => $glabel): ?>
            <h3 class="h6 mt-2"><?= e($glabel) ?></h3>
            <table class="table table-sm a-table align-middle mb-3"><thead><tr><th>Default</th><th>Style (drawing)</th><th>Label shown to customer</th><th>Extra price</th><th>Order</th><th>Active</th><th>Delete</th></tr></thead><tbody>
            <?php foreach ($opts[$group] ?? [] as $o): $oid = (int) $o['id']; ?>
                <tr><td><input class="form-check-input" type="radio" name="default[<?= e($group) ?>]" value="<?= $oid ?>"<?= (int) $o['is_default'] ? ' checked' : '' ?>></td>
                <td class="small text-muted"><?= e($drawable[$group][$o['value_key']] ?? $o['value_key']) ?></td>
                <td><input class="form-control form-control-sm" name="opt[<?= $oid ?>][label]" value="<?= e($o['label']) ?>"></td>
                <td><input class="form-control form-control-sm" style="width:90px" type="number" min="0" step="1" name="opt[<?= $oid ?>][price]" value="<?= e((float) $o['extra_price']) ?>"></td>
                <td><input class="form-control form-control-sm" style="width:60px" type="number" name="opt[<?= $oid ?>][sort]" value="<?= (int) $o['sort_order'] ?>"></td>
                <td><input class="form-check-input" type="checkbox" name="opt[<?= $oid ?>][active]" value="1"<?= (int) $o['is_active'] ? ' checked' : '' ?>></td>
                <td><input class="form-check-input" type="checkbox" name="delete[]" value="<?= $oid ?>"></td></tr>
            <?php endforeach; ?>
            <?php if (empty($opts[$group])): ?><tr><td colspan="7" class="small text-muted">No options — this choice is hidden from customers.</td></tr><?php endif; ?>
            </tbody></table>
        <?php endforeach; ?>
        <button class="btn btn-dark">Save options</button>
        </form>
        <hr>
        <form method="post" class="row g-2 align-items-end"><?= csrf_field() ?><input type="hidden" name="action" value="add_option">
            <div class="col-md-3"><label class="form-label small">Group</label><select class="form-select form-select-sm" name="option_group" id="optGroup"><?php foreach ($def['groups'] as $group => $glabel): ?><option value="<?= e($group) ?>"><?= e($glabel) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label small">Style</label><select class="form-select form-select-sm" name="value_key" id="optKey"></select></div>
            <div class="col-md-3"><label class="form-label small">Label</label><input class="form-control form-control-sm" name="label" placeholder="Optional"></div>
            <div class="col-md-2"><label class="form-label small">Extra price</label><input class="form-control form-control-sm" type="number" min="0" name="extra_price" value="0"></div>
            <div class="col-md-1"><button class="btn btn-sm btn-dark w-100">Add</button></div>
        </form>
        <script>document.addEventListener('DOMContentLoaded',function(){var d=<?= ejs(array_intersect_key($drawable, $def['groups'])) ?>,g=document.getElementById('optGroup'),k=document.getElementById('optKey');function f(){k.innerHTML='';Object.keys(d[g.value]||{}).forEach(function(x){var o=document.createElement('option');o.value=x;o.textContent=d[g.value][x];k.appendChild(o);});}g.addEventListener('change',f);f();});</script>
    </div></div>
</div>
</div>
<?php else: ?><div class="a-empty"><i class="bi bi-rulers"></i><p>No garments found. Import the latest database update (database/migrations).</p></div><?php endif; ?>
<?php admin_footer();
