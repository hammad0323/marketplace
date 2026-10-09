<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('categories.manage');

if (is_post()) {
    require_csrf();
    $id = input_int('id');
    $cat = db_one('SELECT * FROM categories WHERE id = ?', [$id]);
    if ($cat && input('action') === 'delete') {
        $children = (int) db_val('SELECT COUNT(*) FROM categories WHERE parent_id = ?', [$id]);
        $products = (int) db_val('SELECT COUNT(*) FROM products WHERE category_id = ? OR subcategory_id = ?', [$id, $id]);
        $move = input_int('move_to');
        if ($children) {
            flash('error', 'Cannot delete "' . $cat['name'] . '": it has ' . $children . ' subcategories. Move or delete them first.');
        } elseif ($products && !$move) {
            flash('error', 'Cannot delete "' . $cat['name'] . '": ' . $products . ' products are assigned. Choose a category to move them to.');
        } elseif ($move === $id || ($move && !db_val('SELECT COUNT(*) FROM categories WHERE id = ?', [$move]))) {
            flash('error', 'Choose a different, existing category to move the products into.');
        } else {
            db_tx(function () use ($id, $move, $cat) {
                if ($move) {
                    $target = db_one('SELECT * FROM categories WHERE id = ?', [$move]);
                    if ($target['parent_id']) {
                        // Moving into a subcategory: set its parent as category.
                        db_exec('UPDATE products SET category_id = ?, subcategory_id = ? WHERE category_id = ? OR subcategory_id = ?', [$target['parent_id'], $move, $id, $id]);
                    } else {
                        db_exec('UPDATE products SET subcategory_id = NULL WHERE subcategory_id = ?', [$id]);
                        db_exec('UPDATE products SET category_id = ?, subcategory_id = NULL WHERE category_id = ?', [$move, $id]);
                    }
                    add_slug_redirect('category', $cat['slug'], db_val('SELECT slug FROM categories WHERE id = ?', [$move]));
                }
                db_exec('DELETE FROM categories WHERE id = ?', [$id]);
            });
            delete_upload($cat['image']);
            delete_upload($cat['banner_image']);
            audit_log('category_deleted', 'category', $id, ['name' => $cat['name'], 'moved_to' => $move]);
            flash('success', 'Category deleted.');
        }
    }
    redirect(admin_url('categories'));
}

$counts = [];
foreach (db_all('SELECT category_id c, subcategory_id s, COUNT(*) n FROM products GROUP BY category_id, subcategory_id') as $r) {
    $counts[(int) $r['c']] = ($counts[(int) $r['c']] ?? 0) + (int) $r['n'];
    if ($r['s']) {
        $counts[(int) $r['s']] = ($counts[(int) $r['s']] ?? 0) + (int) $r['n'];
    }
}
$tree = category_tree(false);
$moveOpts = category_options(true);

function cat_rows(array $nodes, int $depth, array $counts, array $moveOpts): void
{
    foreach ($nodes as $c): ?>
      <div class="section-row<?= (int) $c['is_active'] ? '' : ' is-disabled' ?>" data-id="<?= (int) $c['id'] ?>" style="margin-left: <?= $depth * 28 ?>px">
        <i class="bi bi-grip-vertical drag-handle"></i>
        <img class="thumb" src="<?= e(media_url($c['image'])) ?>" alt="">
        <div class="flex-grow-1"><a href="<?= e(admin_url('category-edit', ['id' => $c['id']])) ?>"><strong><?= e($c['name']) ?></strong></a> <small class="text-muted">/category/<?= e($c['slug']) ?></small><br>
          <small class="text-muted"><?= (int) ($counts[(int) $c['id']] ?? 0) ?> products<?= (int) $c['show_on_home'] ? ' · on homepage' : '' ?></small></div>
        <div class="form-check form-switch mb-0" title="Active"><input class="form-check-input" type="checkbox" data-toggle-url data-entity="category" data-field="is_active" data-id="<?= (int) $c['id'] ?>" <?= (int) $c['is_active'] ? 'checked' : '' ?>></div>
        <a class="btn btn-sm btn-light" href="<?= e(admin_url('category-edit', ['id' => $c['id']])) ?>"><i class="bi bi-pencil"></i></a>
        <form method="post" class="d-flex gap-1" data-confirm="Delete category &quot;<?= e($c['name']) ?>&quot;?">
          <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
          <?php if (!empty($counts[(int) $c['id']])): ?><select name="move_to" class="form-select form-select-sm" style="width:150px" title="Move products to"><option value="">Move products to…</option><?php foreach ($moveOpts as $k => $l): if (!$k || (int) $k === (int) $c['id']) continue; ?><option value="<?= (int) $k ?>"><?= e($l) ?></option><?php endforeach; ?></select><?php endif; ?>
          <button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button>
        </form>
      </div>
      <?php if ($c['children']): ?><div data-sortable="categories"><?php cat_rows($c['children'], $depth + 1, $counts, $moveOpts); ?></div><?php endif; ?>
    <?php endforeach;
}

admin_header('Categories', 'categories');
?>
<div class="d-flex justify-content-between mb-3"><p class="text-muted mb-0">Drag to reorder within a level. Categories with products or subcategories cannot be deleted until they are moved.</p>
  <a class="btn btn-primary btn-sm" href="<?= e(admin_url('category-edit')) ?>"><i class="bi bi-plus-lg"></i> Add category</a></div>
<div data-sortable="categories"><?php cat_rows($tree, 0, $counts, $moveOpts); ?></div>
<?php admin_footer();
