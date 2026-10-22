<?php
require __DIR__ . '/../includes/init.php';
admin_require('categories');

if (is_post()) {
    $action = input('action', '', 'post');
    if ($action === 'order') {
        foreach ((array) ($_POST['order'] ?? []) as $cid => $ord) {
            db_exec('UPDATE categories SET sort_order = ? WHERE id = ?', [(int) $ord, (int) $cid]);
        }
        audit_log('categories_reordered', 'category');
        flash('success', 'Category order saved.');
    } elseif ($action === 'delete' && ($id = input_int('id'))) {
        $c = db_row('SELECT * FROM categories WHERE id = ?', [$id]);
        $products = (int) db_val('SELECT COUNT(*) FROM products WHERE category_id = ?', [$id]);
        if ($c) {
            // Children move up to the deleted category's parent; products move to the parent too.
            db_exec('UPDATE categories SET parent_id = ? WHERE parent_id = ?', [$c['parent_id'], $id]);
            db_exec('UPDATE products SET category_id = ? WHERE category_id = ?', [$c['parent_id'], $id]);
            delete_upload($c['image']);
            delete_upload($c['banner_image']);
            db_exec('DELETE FROM categories WHERE id = ?', [$id]);
            if ($c['parent_id']) {
                $parent = db_row('SELECT slug FROM categories WHERE id = ?', [(int) $c['parent_id']]);
                redirect_add('/category/' . $c['slug'], '/category/' . $parent['slug']);
            }
            audit_log('category_deleted', 'category', $id, ['name' => $c['name'], 'products_moved' => $products]);
            flash('success', 'Category deleted' . ($products ? " — $products product(s) moved to the parent category." : '.'));
        }
    } elseif ($action === 'toggle' && ($id = input_int('id'))) {
        db_exec('UPDATE categories SET is_active = 1 - is_active WHERE id = ?', [$id]);
        audit_log('category_toggled', 'category', $id);
    }
    redirect('admin/categories/');
}

$counts = array_column(db_all('SELECT category_id, COUNT(*) n FROM products GROUP BY category_id'), 'n', 'category_id');
$rows = [];
$walk = function (array $tree, int $depth) use (&$walk, &$rows) {
    foreach ($tree as $c) {
        $c['depth'] = $depth;
        $rows[] = $c;
        $walk($c['children'], $depth + 1);
    }
};
$walk(category_tree(false), 0);
admin_header('Categories');
?>
<div class="d-flex justify-content-between mb-3">
    <p class="text-muted mb-0">Unlimited nested categories. Set the display order with the numbers and click “Save order”.</p>
    <a class="btn btn-dark" href="<?= e(url('admin/categories/edit.php')) ?>"><i class="bi bi-plus-lg"></i> Add category</a>
</div>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="order">
<div class="card a-card"><div class="table-responsive"><table class="table a-table mb-0">
<thead><tr><th>Category</th><th>Slug</th><th>Products</th><th>Homepage</th><th>Menu</th><th>Status</th><th style="width:90px">Order</th><th class="text-end">Actions</th></tr></thead><tbody>
<?php foreach ($rows as $c): ?>
<tr class="a-tree-row">
    <td><span style="padding-left:<?= $c['depth'] * 22 ?>px"><?= $c['depth'] ? '<span class="text-muted">└</span> ' : '' ?><a href="<?= e(url('admin/categories/edit.php?id=' . $c['id'])) ?>"><strong><?= e($c['name']) ?></strong></a></span></td>
    <td class="small text-muted">/category/<?= e($c['slug']) ?></td>
    <td><?= (int) ($counts[$c['id']] ?? 0) ?></td>
    <td><?= (int) $c['show_on_home'] ? '<i class="bi bi-check-lg text-success"></i>' : '' ?></td>
    <td><?= (int) $c['show_in_menu'] ? '<i class="bi bi-check-lg text-success"></i>' : '' ?></td>
    <td><?= a_status_toggle((bool) $c['is_active']) ?></td>
    <td><input class="form-control form-control-sm" type="number" name="order[<?= (int) $c['id'] ?>]" value="<?= (int) $c['sort_order'] ?>"></td>
    <td class="text-end text-nowrap">
        <a class="btn btn-sm btn-light" href="<?= e(url('category/' . $c['slug'])) ?>" target="_blank"><i class="bi bi-eye"></i></a>
        <a class="btn btn-sm btn-light" href="<?= e(url('admin/categories/edit.php?id=' . $c['id'])) ?>"><i class="bi bi-pencil"></i></a>
        <button class="btn btn-sm btn-light" form="tg<?= (int) $c['id'] ?>" title="Enable / disable"><i class="bi bi-power"></i></button>
        <button class="btn btn-sm btn-light text-danger" form="dl<?= (int) $c['id'] ?>"><i class="bi bi-trash"></i></button>
    </td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
<button class="btn btn-dark">Save order</button>
</form>
<?php foreach ($rows as $c): ?>
<form id="tg<?= (int) $c['id'] ?>" method="post" class="d-none"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"></form>
<form id="dl<?= (int) $c['id'] ?>" method="post" class="d-none" data-confirm="Delete “<?= e($c['name']) ?>”? Subcategories and products move to its parent category."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"></form>
<?php endforeach; ?>
<?php admin_footer();
