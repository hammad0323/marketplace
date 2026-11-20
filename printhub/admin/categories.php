<?php
/** Global printing categories: list, create, edit (subcategories, relevant options, icon), enable/disable, delete. */
require __DIR__ . '/_init.php';
$adminId = (int) current_admin()['id'];
$editId = (int) ($_GET['edit'] ?? 0);
if (is_post()) {
    $a = (string) ($_POST['action'] ?? '');
    $cid = (int) ($_POST['id'] ?? 0);
    if ($a === 'save') {
        $code = strtolower(preg_replace('/[^a-z0-9_]/i', '_', trim((string) ($_POST['code'] ?? ''))));
        $data = ['name' => mb_substr(trim((string) $_POST['name']), 0, 100), 'name_ur' => nullable(mb_substr((string) ($_POST['name_ur'] ?? ''), 0, 100)),
            'description' => nullable(mb_substr((string) ($_POST['description'] ?? ''), 0, 255)), 'icon' => preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($_POST['icon'] ?? 'printer'))) ?: 'printer',
            'pricing_basis' => ($_POST['pricing_basis'] ?? 'pages') === 'items' ? 'items' : 'pages', 'accepts_upload' => !empty($_POST['accepts_upload']) ? 1 : 0,
            'sort_order' => (int) ($_POST['sort_order'] ?? 0), 'is_active' => !empty($_POST['is_active']) ? 1 : 0];
        if ($data['name'] === '' || $code === '') {
            flash('error', 'Name and code are required.');
            redirect('admin/categories.php' . ($cid ? '?edit=' . $cid : ''));
        }
        if (db_val('SELECT 1 FROM categories WHERE code = ? AND id <> ?', [$code, $cid])) {
            flash('error', 'Another category already uses that code.');
            redirect('admin/categories.php' . ($cid ? '?edit=' . $cid : ''));
        }
        if (!empty($_FILES['icon_file']['name'])) {
            try {
                $data['icon_path'] = media_store_image($_FILES['icon_file'], 'icons', 256);
            } catch (RuntimeException $e) {
                flash('error', $e->getMessage());
            }
        }
        if ($cid) {
            db_update('categories', $data + ['code' => $code], 'id = ?', [$cid]);
        } else {
            $cid = db_insert('categories', $data + ['code' => $code]);
            // offer the new category in every shop (owners can switch it off)
            db_exec('INSERT IGNORE INTO shop_services (shop_id, category_id, is_enabled) SELECT id, ?, 1 FROM shops WHERE deleted_at IS NULL', [$cid]);
        }
        // relevant options
        db_exec('DELETE FROM category_options WHERE category_id = ?', [$cid]);
        $defaults = array_map('intval', (array) ($_POST['default_opt'] ?? []));
        foreach (array_unique(array_map('intval', (array) ($_POST['opt'] ?? []))) as $oid) {
            if (db_val('SELECT 1 FROM printing_options WHERE id = ?', [$oid])) {
                db_insert('category_options', ['category_id' => $cid, 'option_id' => $oid, 'is_default' => in_array($oid, $defaults, true) ? 1 : 0]);
            }
        }
        activity('admin', $adminId, 'category_saved', null, 'category', $cid);
        flash('success', 'Category saved.');
        redirect('admin/categories.php?edit=' . $cid);
    }
    if ($a === 'toggle') {
        db_exec('UPDATE categories SET is_active = 1 - is_active WHERE id = ?', [$cid]);
        flash('success', 'Category updated.');
    }
    if ($a === 'delete') {
        db_exec('UPDATE categories SET deleted_at = NOW(), is_active = 0 WHERE id = ?', [$cid]);
        activity('admin', $adminId, 'category_deleted', null, 'category', $cid);
        flash('success', 'Category deleted. Past orders keep their details.');
    }
    if ($a === 'sub_save') {
        $sid = (int) ($_POST['sub_id'] ?? 0);
        $code = strtolower(preg_replace('/[^a-z0-9_]/i', '_', trim((string) ($_POST['sub_code'] ?? ''))));
        $data = ['name' => mb_substr(trim((string) ($_POST['sub_name'] ?? '')), 0, 100), 'name_ur' => nullable(mb_substr((string) ($_POST['sub_name_ur'] ?? ''), 0, 100)),
            'pricing_basis' => in_array($_POST['sub_basis'] ?? '', ['pages', 'items'], true) ? $_POST['sub_basis'] : null, 'sort_order' => (int) ($_POST['sub_sort'] ?? 0),
            'is_active' => !empty($_POST['sub_active']) ? 1 : 0];
        if ($data['name'] !== '' && $code !== '') {
            if ($sid) {
                db_update('subcategories', $data + ['code' => $code], 'id = ? AND category_id = ?', [$sid, $cid]);
            } elseif (!db_val('SELECT 1 FROM subcategories WHERE category_id = ? AND code = ?', [$cid, $code])) {
                db_insert('subcategories', $data + ['code' => $code, 'category_id' => $cid]);
            }
            flash('success', 'Subcategory saved.');
        } else {
            flash('error', 'Subcategory name and code are required.');
        }
    }
    if ($a === 'sub_delete') {
        db_exec('UPDATE subcategories SET deleted_at = NOW(), is_active = 0 WHERE id = ? AND category_id = ?', [(int) ($_POST['sub_id'] ?? 0), $cid]);
        flash('success', 'Subcategory deleted.');
    }
    redirect('admin/categories.php' . (in_array($a, ['sub_save', 'sub_delete'], true) || $editId ? '?edit=' . $cid : ''));
}
$cats = db_all('SELECT c.*, (SELECT COUNT(*) FROM subcategories s WHERE s.category_id = c.id AND s.deleted_at IS NULL) subs, (SELECT COUNT(*) FROM category_options co WHERE co.category_id = c.id) opts,
    (SELECT COUNT(*) FROM shop_services ss WHERE ss.category_id = c.id AND ss.subcategory_id IS NULL AND ss.is_enabled = 1) shops
    FROM categories c WHERE c.deleted_at IS NULL ORDER BY c.sort_order, c.id');
$edit = $editId ? db_row('SELECT * FROM categories WHERE id = ? AND deleted_at IS NULL', [$editId]) : null;
$isNew = isset($_GET['new']);
$options = db_all('SELECT * FROM printing_options WHERE is_active = 1 ORDER BY FIELD(option_group, "paper_size","color","sides","paper_type","gsm","finishing"), sort_order');
$groups = ['paper_size' => 'Paper sizes', 'color' => 'Printing colour', 'sides' => 'Sides', 'paper_type' => 'Paper types', 'gsm' => 'Paper GSM', 'finishing' => 'Finishing services'];
$chosen = $edit ? array_column(db_all('SELECT option_id, is_default FROM category_options WHERE category_id = ?', [$editId]), 'is_default', 'option_id') : [];
admin_header($edit ? 'Category: ' . $edit['name'] : ($isNew ? 'New category' : 'Printing categories'));
?>
<?php if ($edit || $isNew): $c = $edit ?: ['code' => '', 'name' => '', 'name_ur' => '', 'description' => '', 'icon' => 'printer', 'pricing_basis' => 'pages', 'accepts_upload' => 1, 'sort_order' => count($cats) + 1, 'is_active' => 1, 'icon_path' => null]; ?>
<a class="btn btn-light btn-sm mb-3" href="<?= e(path('admin/categories.php')) ?>"><i class="bi bi-arrow-left"></i> All categories</a>
<form method="post" enctype="multipart/form-data" class="row g-3"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
<div class="col-xl-5"><div class="p-card"><div class="p-card-head">Category</div><div class="p-card-body">
    <div class="row"><div class="col-7"><?= f_input('name', 'Name', $c['name'], 'text', ['required' => true]) ?></div><div class="col-5"><?= f_input('code', 'Code', $c['code'], 'text', ['required' => true, 'pattern' => '[a-z0-9_]+']) ?></div></div>
    <?= f_input('name_ur', 'Urdu name (optional)', $c['name_ur'], 'text', ['dir' => 'rtl']) ?>
    <?= f_textarea('description', 'Description', $c['description'], 2) ?>
    <div class="row"><div class="col-6"><?= f_input('icon', 'Icon name', $c['icon'], 'text', [], 'A <a href="https://icons.getbootstrap.com" target="_blank" rel="noopener">Bootstrap icon</a> name, e.g. <code>printer</code>') ?></div>
        <div class="col-6"><div class="mb-3"><label class="form-label">or upload an icon</label><input class="form-control" type="file" name="icon_file" accept="image/png,image/webp,image/jpeg"><?php if ($c['icon_path']): ?><img src="<?= e(path('media/' . $c['icon_path'])) ?>" alt="" height="28" class="mt-1"><?php endif; ?></div></div></div>
    <?= f_select('pricing_basis', 'Priced by', ['pages' => 'Pages (documents)', 'items' => 'Pieces (cards, photos, sets)'], $c['pricing_basis'], 'Pieces = customers enter a quantity instead of pages.') ?>
    <div class="row"><div class="col-6"><?= f_input('sort_order', 'Order', $c['sort_order'], 'number') ?></div><div class="col-6 pt-4"><?= f_switch('is_active', 'Enabled', (int) $c['is_active']) ?></div></div>
    <?= f_switch('accepts_upload', 'Customers upload a file', (int) $c['accepts_upload']) ?>
</div></div></div>
<div class="col-xl-7"><div class="p-card"><div class="p-card-head">Options customers see for this category <span class="ms-auto small text-muted">✓ offered · ★ selected by default</span></div><div class="p-card-body">
    <?php foreach ($groups as $g => $label): ?>
        <p class="fw-semibold small mb-1 mt-2"><?= e($label) ?></p><div class="d-flex flex-wrap gap-2 mb-2">
        <?php foreach ($options as $o): if ($o['option_group'] !== $g) continue; $on = array_key_exists($o['id'], $chosen); ?>
            <span class="p-chip"><label class="d-inline-flex align-items-center gap-1"><input class="form-check-input mt-0" type="checkbox" name="opt[]" value="<?= (int) $o['id'] ?>"<?= $on ? ' checked' : '' ?>> <?= e($o['label']) ?></label>
            <label title="Default" class="ms-1"><input class="form-check-input mt-0" type="checkbox" name="default_opt[]" value="<?= (int) $o['id'] ?>"<?= !empty($chosen[$o['id']]) ? ' checked' : '' ?>>★</label></span>
        <?php endforeach; ?></div>
    <?php endforeach; ?>
    <p class="form-text">Only checked options are shown to customers — e.g. leave binding off for passport photos. Shops can hide options they don’t offer.</p>
</div></div><button class="btn btn-primary">Save category</button></div>
</form>
<?php if ($edit): $subs = db_all('SELECT * FROM subcategories WHERE category_id = ? AND deleted_at IS NULL ORDER BY sort_order, id', [$editId]); ?>
<div class="p-card mt-4"><div class="p-card-head">Subcategories</div><div class="table-responsive"><table class="table p-table align-middle"><thead><tr><th>Name</th><th>Code</th><th>Urdu</th><th>Priced by</th><th>Order</th><th>On</th><th></th></tr></thead><tbody>
<?php foreach (array_merge($subs, [null]) as $s): $fid = 'sf' . ($s['id'] ?? 'new'); ?>
<tr><td><input form="<?= $fid ?>" class="form-control form-control-sm" name="sub_name" value="<?= e($s['name'] ?? '') ?>"<?= $s ? '' : ' placeholder="New subcategory" required' ?>></td>
<td><input form="<?= $fid ?>" class="form-control form-control-sm" name="sub_code" value="<?= e($s['code'] ?? '') ?>" style="width:140px"<?= $s ? '' : ' placeholder="code" required' ?>></td>
<td><input form="<?= $fid ?>" class="form-control form-control-sm" name="sub_name_ur" value="<?= e($s['name_ur'] ?? '') ?>" dir="rtl" style="width:140px"></td>
<td><select form="<?= $fid ?>" class="form-select form-select-sm" name="sub_basis"><option value="">Same as category</option><option value="pages"<?= ($s['pricing_basis'] ?? '') === 'pages' ? ' selected' : '' ?>>Pages</option><option value="items"<?= ($s['pricing_basis'] ?? '') === 'items' ? ' selected' : '' ?>>Pieces</option></select></td>
<td><input form="<?= $fid ?>" class="form-control form-control-sm" type="number" name="sub_sort" value="<?= (int) ($s['sort_order'] ?? count($subs) + 1) ?>" style="width:70px"></td>
<td><input form="<?= $fid ?>" class="form-check-input" type="checkbox" name="sub_active" value="1"<?= !$s || (int) $s['is_active'] ? ' checked' : '' ?>></td>
<td class="text-nowrap"><button form="<?= $fid ?>" class="btn btn-sm <?= $s ? 'btn-light' : 'btn-primary' ?>" title="Save"><i class="bi bi-<?= $s ? 'check2' : 'plus' ?>"></i></button>
<?php if ($s): ?><button form="sd<?= (int) $s['id'] ?>" class="btn btn-sm btn-light text-danger" title="Delete"><i class="bi bi-trash"></i></button><?php endif; ?></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php foreach (array_merge($subs, [null]) as $s): ?><form id="sf<?= $s['id'] ?? 'new' ?>" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="sub_save"><input type="hidden" name="id" value="<?= (int) $editId ?>"><input type="hidden" name="sub_id" value="<?= (int) ($s['id'] ?? 0) ?>"></form><?php endforeach; ?>
<?php foreach ($subs as $s): ?><form id="sd<?= (int) $s['id'] ?>" method="post" data-confirm="Delete this subcategory?"><?= csrf_field() ?><input type="hidden" name="action" value="sub_delete"><input type="hidden" name="id" value="<?= (int) $editId ?>"><input type="hidden" name="sub_id" value="<?= (int) $s['id'] ?>"></form><?php endforeach; ?>
<?php endif; ?>
<?php else: ?>
<div class="d-flex mb-3"><p class="text-muted mb-0">The master list every shop chooses from. Shops switch on only the services they offer.</p><a class="btn btn-primary ms-auto" href="?new=1"><i class="bi bi-plus-lg"></i> Add category</a></div>
<div class="p-card"><div class="table-responsive"><table class="table p-table"><thead><tr><th>Category</th><th>Code</th><th>Priced by</th><th>Subcategories</th><th>Options</th><th>Shops offering</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($cats as $c): ?>
<tr><td><div class="d-flex align-items-center gap-2"><?php if ($c['icon_path']): ?><img src="<?= e(path('media/' . $c['icon_path'])) ?>" class="p-thumb" alt=""><?php else: ?><span class="p-thumb d-inline-flex align-items-center justify-content-center"><i class="bi bi-<?= e($c['icon']) ?>" style="color:#0F766E"></i></span><?php endif; ?>
    <div><a href="?edit=<?= (int) $c['id'] ?>"><strong><?= e($c['name']) ?></strong></a><div class="small text-muted"><?= e(str_limit($c['description'], 60)) ?></div></div></div></td>
<td><code><?= e($c['code']) ?></code></td><td><?= $c['pricing_basis'] === 'items' ? 'Pieces' : 'Pages' ?></td><td><?= (int) $c['subs'] ?></td><td><?= (int) $c['opts'] ?></td><td><?= (int) $c['shops'] ?></td>
<td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn btn-sm <?= (int) $c['is_active'] ? 'btn-light text-success' : 'btn-light text-muted' ?>"><i class="bi bi-toggle-<?= (int) $c['is_active'] ? 'on' : 'off' ?> fs-5"></i></button></form></td>
<td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int) $c['id'] ?>">Edit</a>
<form method="post" class="d-inline" data-confirm="Delete “<?= e($c['name']) ?>”? Shops will no longer offer it."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>
<?php admin_footer();
