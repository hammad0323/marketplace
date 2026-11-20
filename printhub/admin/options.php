<?php
/** Master printing options: paper sizes (with dimensions), colour, sides, paper types, GSM, finishing services. */
require __DIR__ . '/_init.php';
$groups = ['paper_size' => 'Paper sizes', 'color' => 'Printing colour', 'sides' => 'Printing sides', 'paper_type' => 'Paper types', 'gsm' => 'Paper GSM', 'finishing' => 'Additional services'];
if (is_post()) {
    $a = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    if ($a === 'save') {
        $group = (string) ($_POST['option_group'] ?? '');
        $code = strtolower(preg_replace('/[^a-z0-9_]/i', '_', trim((string) ($_POST['code'] ?? ''))));
        $data = ['label' => mb_substr(trim((string) ($_POST['label'] ?? '')), 0, 80), 'label_ur' => nullable(mb_substr((string) ($_POST['label_ur'] ?? ''), 0, 80)),
            'width_mm' => is_numeric($_POST['width_mm'] ?? '') ? max(1, min(2000, (float) $_POST['width_mm'])) : null,
            'height_mm' => is_numeric($_POST['height_mm'] ?? '') ? max(1, min(2000, (float) $_POST['height_mm'])) : null,
            'sort_order' => (int) ($_POST['sort_order'] ?? 0), 'is_active' => !empty($_POST['is_active']) ? 1 : 0];
        if (!isset($groups[$group]) || $data['label'] === '' || $code === '') {
            flash('error', 'Group, label and code are required.');
        } elseif (in_array($group, ['color', 'sides'], true) && !$id) {
            flash('error', 'Colour and sides are fixed options.');
        } elseif (db_val('SELECT 1 FROM printing_options WHERE option_group = ? AND code = ? AND id <> ?', [$group, $code, $id])) {
            flash('error', 'That code already exists in this group.');
        } else {
            if ($id) {
                db_update('printing_options', $data + ['code' => $code], 'id = ? AND option_group = ?', [$id, $group]);
            } else {
                db_insert('printing_options', $data + ['code' => $code, 'option_group' => $group]);
            }
            activity('admin', (int) current_admin()['id'], 'option_saved', null, 'printing_option', $id ?: null);
            flash('success', 'Option saved.');
        }
    }
    redirect('admin/options.php#g-' . ($_POST['option_group'] ?? ''));
}
$all = db_all('SELECT o.*, (SELECT COUNT(*) FROM category_options co WHERE co.option_id = o.id) cats FROM printing_options o ORDER BY sort_order, id');
admin_header('Printing options');
?>
<p class="text-muted">These are the choices customers pick from. Assign them to categories under <a href="<?= e(path('admin/categories.php')) ?>">Categories</a>; each shop sets its own prices.</p>
<div class="row g-3">
<?php foreach ($groups as $g => $label): $rows = array_values(array_filter($all, fn($o) => $o['option_group'] === $g)); ?>
<div class="col-xl-6" id="g-<?= $g ?>"><div class="p-card"><div class="p-card-head"><?= e($label) ?></div><div class="table-responsive"><table class="table p-table align-middle"><thead><tr><th>Label</th><th>Code</th><?php if ($g === 'paper_size'): ?><th>W × H mm</th><?php endif; ?><th>Order</th><th>On</th><th></th></tr></thead><tbody>
<?php foreach (array_merge($rows, in_array($g, ['color', 'sides'], true) ? [] : [null]) as $o): $fid = 'o' . ($o['id'] ?? 'new' . $g); ?>
<tr><td><input form="<?= $fid ?>" class="form-control form-control-sm" name="label" value="<?= e($o['label'] ?? '') ?>"<?= $o ? '' : ' placeholder="Add…" required' ?>></td>
<td><input form="<?= $fid ?>" class="form-control form-control-sm" name="code" value="<?= e($o['code'] ?? '') ?>" style="width:110px"<?= $o ? '' : ' required' ?><?= in_array($g, ['color', 'sides'], true) ? ' readonly' : '' ?>></td>
<?php if ($g === 'paper_size'): ?><td class="text-nowrap"><input form="<?= $fid ?>" class="form-control form-control-sm d-inline-block" style="width:70px" name="width_mm" value="<?= e($o['width_mm'] ?? '') ?>">×<input form="<?= $fid ?>" class="form-control form-control-sm d-inline-block" style="width:70px" name="height_mm" value="<?= e($o['height_mm'] ?? '') ?>"></td><?php endif; ?>
<td><input form="<?= $fid ?>" class="form-control form-control-sm" type="number" name="sort_order" value="<?= (int) ($o['sort_order'] ?? count($rows) + 1) ?>" style="width:64px"></td>
<td><input form="<?= $fid ?>" class="form-check-input" type="checkbox" name="is_active" value="1"<?= !$o || (int) $o['is_active'] ? ' checked' : '' ?>></td>
<td><button form="<?= $fid ?>" class="btn btn-sm <?= $o ? 'btn-light' : 'btn-primary' ?>"><i class="bi bi-<?= $o ? 'check2' : 'plus' ?>"></i></button></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php foreach (array_merge($rows, [null]) as $o): ?><form id="o<?= $o['id'] ?? 'new' . $g ?>" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($o['id'] ?? 0) ?>"><input type="hidden" name="option_group" value="<?= $g ?>"></form><?php endforeach; ?>
</div></div>
<?php endforeach; ?>
</div>
<?php admin_footer();
