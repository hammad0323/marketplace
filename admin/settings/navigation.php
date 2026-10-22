<?php
/** Menu editor for the header (with dropdowns & mega menus) and the three footer columns. */
require __DIR__ . '/../includes/init.php';
admin_require('navigation');
$menus = ['header' => 'Header navigation', 'footer_shop' => 'Footer — Shop', 'footer_help' => 'Footer — Customer Care', 'footer_about' => 'Footer — Urban Collections'];
$menu = isset($menus[input('menu', 'header')]) ? input('menu', 'header') : 'header';
if (is_post()) {
    $a = input('action', '', 'post');
    if ($a === 'save') {
        foreach ((array) ($_POST['item'] ?? []) as $iid => $it) {
            $url = trim((string) ($it['url'] ?? ''));
            if ($url !== '' && !preg_match('#^(https?://|/|\#|mailto:|tel:)#i', $url) && !preg_match('#^[a-z0-9\-/\?=&_\.]+$#i', $url)) $url = '';
            db_update('navigation_items', [
                'label' => mb_substr(trim((string) ($it['label'] ?? '')), 0, 80) ?: 'Link', 'url' => $url,
                'parent_id' => (int) ($it['parent_id'] ?? 0) ?: null, 'category_id' => (int) ($it['category_id'] ?? 0) ?: null,
                'is_mega' => !empty($it['is_mega']) ? 1 : 0, 'highlight' => !empty($it['highlight']) ? 1 : 0, 'open_new_tab' => !empty($it['new_tab']) ? 1 : 0,
                'is_active' => !empty($it['active']) ? 1 : 0, 'sort_order' => (int) ($it['sort'] ?? 0),
            ], 'id = ? AND menu = ?', [(int) $iid, $menu]);
        }
        foreach (array_keys($_FILES['mega']['name'] ?? []) as $iid) {
            $file = ['name' => $_FILES['mega']['name'][$iid], 'type' => $_FILES['mega']['type'][$iid], 'tmp_name' => $_FILES['mega']['tmp_name'][$iid], 'error' => $_FILES['mega']['error'][$iid], 'size' => $_FILES['mega']['size'][$iid]];
            $p = a_try(fn() => upload_image('mega', 1200, $file));
            if ($p) db_exec('UPDATE navigation_items SET mega_image = ? WHERE id = ?', [$p, (int) $iid]);
        }
        flash('success', 'Menu saved.');
    } elseif ($a === 'add') {
        db_insert('navigation_items', ['menu' => $menu, 'label' => mb_substr(input('label', 'New link', 'post'), 0, 80), 'url' => mb_substr(input('url', '', 'post'), 0, 500),
            'parent_id' => input_int('parent_id') ?: null, 'sort_order' => (int) db_val('SELECT COALESCE(MAX(sort_order),0)+1 FROM navigation_items WHERE menu = ?', [$menu])]);
        flash('success', 'Link added.');
    } elseif ($a === 'delete') {
        db_exec('DELETE FROM navigation_items WHERE id = ? AND menu = ?', [input_int('id'), $menu]);
    }
    audit_log('navigation_' . $a, 'navigation', null, ['menu' => $menu]);
    redirect('admin/settings/navigation.php?menu=' . $menu);
}
$items = db_all('SELECT * FROM navigation_items WHERE menu = ? ORDER BY parent_id IS NOT NULL, sort_order, id', [$menu]);
$tops = array_filter($items, fn($i) => !$i['parent_id']);
$ordered = [];
foreach ($tops as $t) {
    $ordered[] = $t;
    foreach ($items as $c) if ((int) $c['parent_id'] === (int) $t['id']) $ordered[] = $c;
}
$catOpts = ['' => '—'];
foreach (category_options(false) as $o) $catOpts[$o['id']] = $o['label'];
admin_header('Navigation');
?>
<ul class="nav a-tabs"><?php foreach ($menus as $k => $l): ?><li class="nav-item"><a class="nav-link<?= $menu === $k ? ' active' : '' ?>" href="?menu=<?= $k ?>"><?= e($l) ?></a></li><?php endforeach; ?></ul>
<?php if ($menu === 'header'): ?><div class="a-help mb-3">Links use store paths like <code>shop</code>, <code>category/shirts</code>, <code>collections/formal</code>, <code>about-us</code> or full URLs. For a <b>mega menu</b>, choose a category and tick “Mega” — its subcategories appear automatically with the feature image. For a simple dropdown, add child links under a parent.</div><?php endif; ?>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="save">
<div class="card a-card"><div class="table-responsive"><table class="table a-table mb-0"><thead><tr><th>Label</th><th>Link</th><th>Parent</th><?php if ($menu === 'header'): ?><th>Mega category</th><th>Mega image</th><?php endif; ?><th>Options</th><th style="width:70px">Order</th><th></th></tr></thead><tbody>
<?php foreach ($ordered as $it): $iid = (int) $it['id']; ?>
<tr>
    <td><?= $it['parent_id'] ? '<span class="text-muted">└</span> ' : '' ?><input class="form-control form-control-sm d-inline-block" style="width:<?= $it['parent_id'] ? '85' : '100' ?>%" name="item[<?= $iid ?>][label]" value="<?= e($it['label']) ?>"></td>
    <td><input class="form-control form-control-sm" name="item[<?= $iid ?>][url]" value="<?= e($it['url']) ?>"></td>
    <td><select class="form-select form-select-sm" name="item[<?= $iid ?>][parent_id]"><option value="">— top —</option><?php foreach ($tops as $t): if ((int) $t['id'] === $iid) continue; ?><option value="<?= (int) $t['id'] ?>"<?= (int) $it['parent_id'] === (int) $t['id'] ? ' selected' : '' ?>><?= e($t['label']) ?></option><?php endforeach; ?></select></td>
    <?php if ($menu === 'header'): ?>
    <td><select class="form-select form-select-sm" name="item[<?= $iid ?>][category_id]"><?php foreach ($catOpts as $k => $l): ?><option value="<?= e($k) ?>"<?= (string) $it['category_id'] === (string) $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></td>
    <td><div class="d-flex gap-1 align-items-center"><?php if ($it['mega_image']): ?><img src="<?= e(img_sized($it['mega_image'], 100)) ?>" width="36" height="44" style="object-fit:cover" alt=""><?php endif; ?><input class="form-control form-control-sm" type="file" name="mega[<?= $iid ?>]" accept="image/*" style="max-width:180px"></div></td>
    <?php endif; ?>
    <td class="small text-nowrap">
        <label><input type="checkbox" name="item[<?= $iid ?>][active]" value="1"<?= (int) $it['is_active'] ? ' checked' : '' ?>> Active</label><br>
        <?php if ($menu === 'header'): ?><label><input type="checkbox" name="item[<?= $iid ?>][is_mega]" value="1"<?= (int) $it['is_mega'] ? ' checked' : '' ?>> Mega</label>
        <label><input type="checkbox" name="item[<?= $iid ?>][highlight]" value="1"<?= (int) $it['highlight'] ? ' checked' : '' ?>> Highlight</label><br><?php endif; ?>
        <label><input type="checkbox" name="item[<?= $iid ?>][new_tab]" value="1"<?= (int) $it['open_new_tab'] ? ' checked' : '' ?>> New tab</label></td>
    <td><input class="form-control form-control-sm" type="number" name="item[<?= $iid ?>][sort]" value="<?= (int) $it['sort_order'] ?>"></td>
    <td><button class="btn btn-sm btn-light text-danger" form="del<?= $iid ?>"><i class="bi bi-trash"></i></button></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
<button class="btn btn-dark">Save menu</button>
</form>
<div class="card a-card mt-4"><div class="card-header">Add link</div><div class="card-body">
<form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="action" value="add">
    <div class="col-md-3"><input class="form-control" name="label" placeholder="Label" required></div>
    <div class="col-md-4"><input class="form-control" name="url" placeholder="Link, e.g. category/shirts"></div>
    <div class="col-md-3"><select class="form-select" name="parent_id"><option value="">Top level</option><?php foreach ($tops as $t): ?><option value="<?= (int) $t['id'] ?>">Under: <?= e($t['label']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><button class="btn btn-dark w-100">Add</button></div>
</form></div></div>
<?php foreach ($ordered as $it): ?><form id="del<?= (int) $it['id'] ?>" method="post" class="d-none" data-confirm="Delete “<?= e($it['label']) ?>”<?= $it['parent_id'] ? '' : ' and its child links' ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $it['id'] ?>"></form><?php endforeach; ?>
<?php admin_footer();
