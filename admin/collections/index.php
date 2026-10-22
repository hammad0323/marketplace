<?php
require __DIR__ . '/../includes/init.php';
admin_require('collections');
if (is_post()) {
    $action = input('action', '', 'post');
    if ($action === 'order') {
        foreach ((array) ($_POST['order'] ?? []) as $cid => $ord) {
            db_exec('UPDATE collections SET sort_order = ? WHERE id = ?', [(int) $ord, (int) $cid]);
        }
        flash('success', 'Order saved.');
    } elseif ($action === 'delete' && ($id = input_int('id'))) {
        $c = db_row('SELECT * FROM collections WHERE id = ?', [$id]);
        if ($c) {
            delete_upload($c['image']);
            delete_upload($c['banner_image']);
            db_exec('DELETE FROM collections WHERE id = ?', [$id]);
            redirect_add('/collections/' . $c['slug'], '/collections');
            audit_log('collection_deleted', 'collection', $id, ['name' => $c['name']]);
            flash('success', 'Collection deleted.');
        }
    }
    redirect('admin/collections/');
}
$rows = db_all('SELECT c.*, (SELECT COUNT(*) FROM collection_products cp WHERE cp.collection_id = c.id) n FROM collections c ORDER BY sort_order, name');
admin_header('Collections');
?>
<div class="d-flex justify-content-between mb-3"><p class="text-muted mb-0">Style-based collections such as Smart Casual, Streetwear or Winter.</p>
<a class="btn btn-dark" href="<?= e(url('admin/collections/edit.php')) ?>"><i class="bi bi-plus-lg"></i> Add collection</a></div>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="order">
<div class="card a-card"><div class="table-responsive"><table class="table a-table mb-0">
<thead><tr><th></th><th>Collection</th><th>Products</th><th>Homepage</th><th>Status</th><th style="width:90px">Order</th><th class="text-end">Actions</th></tr></thead><tbody>
<?php foreach ($rows as $c): ?>
<tr><td><div class="a-thumb a-thumb-sm"><?php if ($c['image']): ?><img src="<?= e(img_sized($c['image'], 120)) ?>" alt=""><?php endif; ?></div></td>
<td><a href="<?= e(url('admin/collections/edit.php?id=' . $c['id'])) ?>"><strong><?= e($c['name']) ?></strong></a><div class="small text-muted">/collections/<?= e($c['slug']) ?></div></td>
<td><?= (int) $c['n'] ?></td><td><?= (int) $c['show_on_home'] ? '<i class="bi bi-check-lg text-success"></i>' : '' ?></td><td><?= a_status_toggle((bool) $c['is_active']) ?></td>
<td><input class="form-control form-control-sm" type="number" name="order[<?= (int) $c['id'] ?>]" value="<?= (int) $c['sort_order'] ?>"></td>
<td class="text-end text-nowrap"><a class="btn btn-sm btn-light" href="<?= e(url('collections/' . $c['slug'])) ?>" target="_blank"><i class="bi bi-eye"></i></a>
<a class="btn btn-sm btn-light" href="<?= e(url('admin/collections/edit.php?id=' . $c['id'])) ?>"><i class="bi bi-pencil"></i></a>
<button class="btn btn-sm btn-light text-danger" form="dl<?= (int) $c['id'] ?>"><i class="bi bi-trash"></i></button></td></tr>
<?php endforeach; ?>
</tbody></table></div></div><button class="btn btn-dark">Save order</button></form>
<?php foreach ($rows as $c): ?><form id="dl<?= (int) $c['id'] ?>" method="post" class="d-none" data-confirm="Delete collection “<?= e($c['name']) ?>”? Products are not deleted."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"></form><?php endforeach; ?>
<?php admin_footer();
