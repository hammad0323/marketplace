<?php
require __DIR__ . '/includes/init.php';
admin_require('categories');

if (is_post()) {
    csrf_check();
    $act = input('act');
    if ($act === 'reorder') {
        foreach (input_arr('order') as $i => $id) db_exec('UPDATE categories SET sort_order = ? WHERE id = ?', [$i, (int) $id]);
        catalog_changed();
        json_out(['ok' => true]);
    }
    if ($act === 'toggle') {
        db_exec('UPDATE categories SET is_active = 1 - is_active WHERE id = ?', [input_int('id')]);
        catalog_changed();
        redirect('admin/categories.php');
    }
    if ($act === 'delete') {
        $id = input_int('id');
        if (db_val('SELECT COUNT(*) FROM categories WHERE parent_id = ? AND deleted_at IS NULL', [$id])) {
            flash('danger', 'This category has subcategories. Move or delete them first.');
        } else {
            $c = db_row('SELECT * FROM categories WHERE id = ?', [$id]);
            // keep products, just unlink; soft delete + free the slug
            db_exec('DELETE FROM product_categories WHERE category_id = ?', [$id]);
            db_exec('UPDATE products SET primary_category_id = NULL WHERE primary_category_id = ?', [$id]);
            db_exec('UPDATE categories SET deleted_at = NOW(), is_active = 0, slug = CONCAT(slug, "-deleted-", id) WHERE id = ?', [$id]);
            flash('success', 'Category "' . ($c['name'] ?? '') . '" deleted. Its products were kept.');
        }
        catalog_changed();
        redirect('admin/categories.php');
    }
    // save
    $id = input_int('id');
    $old = $id ? db_row('SELECT * FROM categories WHERE id = ?', [$id]) : null;
    $name = input('name');
    $parent = input_int('parent_id') ?: null;
    if ($name === '') { flash('danger', 'Name is required.'); redirect('admin/categories.php' . ($id ? '?edit=' . $id : '?new=1')); }
    if ($id && $parent) {
        // prevent cycles
        if ($parent === $id || in_array($parent, category_descendant_ids($id, false), true)) { flash('danger', 'A category cannot be moved inside itself.'); redirect('admin/categories.php?edit=' . $id); }
    }
    $oldUrl = $old ? category_url($old) : null;
    $slug = unique_slug('categories', input('slug') ?: $name, $id);
    $data = [
        'parent_id' => $parent, 'name' => mb_substr($name, 0, 150), 'name_urdu' => input('name_urdu') ?: null, 'slug' => $slug,
        'description' => input('description') ?: null, 'icon' => preg_replace('/[^a-z0-9\-]/', '', input('icon')) ?: null,
        'sort_order' => input_int('sort_order'), 'is_active' => input_int('is_active'), 'show_in_menu' => input_int('show_in_menu'), 'show_on_home' => input_int('show_on_home'),
        'image_alt' => input('image_alt') ?: null,
    ];
    $data['image'] = admin_save_image('image', 'categories', $old['image'] ?? null, 800);
    if ($id) db_update_row('categories', $data, 'id = ?', [$id]);
    else $id = db_insert_row('categories', $data);
    seo_save('category', $id, input_arr('seo'));
    categories_all(true);
    if ($oldUrl && $oldUrl !== category_url($id)) {
        seo_add_redirect($oldUrl, category_url($id));
    }
    // search index includes category names
    if ($old && ($old['name'] !== $data['name'] || $old['name_urdu'] !== $data['name_urdu'] || (int) $old['parent_id'] !== (int) $parent)) {
        foreach (db_col('SELECT product_id FROM product_categories WHERE category_id IN (' . in_placeholders(category_descendant_ids($id, false)) . ')', category_descendant_ids($id, false)) as $pid) product_rebuild_search((int) $pid);
    }
    catalog_changed();
    flash('success', 'Category saved.');
    redirect('admin/categories.php');
}

$editId = input_int('edit');
$edit = $editId ? db_row('SELECT * FROM categories WHERE id = ? AND deleted_at IS NULL', [$editId]) : null;
$showForm = $edit || input('new') === '1';
$counts = [];
foreach (db_all('SELECT category_id, COUNT(*) c FROM product_categories GROUP BY category_id') as $r) $counts[(int) $r['category_id']] = (int) $r['c'];

function cat_tree_html(?int $parent, array $counts): string
{
    $kids = category_children($parent, false);
    if (!$kids) return '';
    $h = '<ul class="js-sortable" data-parent="' . (int) $parent . '">';
    foreach ($kids as $c) {
        $h .= '<li data-id="' . (int) $c['id'] . '"><div class="row-item' . ($c['is_active'] ? '' : ' inactive') . '">'
            . '<i class="bi bi-grip-vertical drag-handle" title="Drag to reorder"></i>'
            . '<img src="' . e(category_image_url($c)) . '" alt="">'
            . '<div class="flex-grow-1"><strong>' . e($c['name']) . '</strong> <small class="text-muted">/' . e($c['slug']) . '</small>'
            . ($c['show_on_home'] ? ' <span class="badge bg-info-subtle text-info">Home</span>' : '') . (!$c['show_in_menu'] ? ' <span class="badge bg-light text-muted">Hidden in menu</span>' : '')
            . '<br><small class="text-muted">' . ($counts[(int) $c['id']] ?? 0) . ' direct products · ' . category_product_count((int) $c['id']) . ' incl. subcategories</small></div>'
            . '<a class="btn btn-sm btn-light" href="' . e(category_url($c)) . '" target="_blank" title="View"><i class="bi bi-box-arrow-up-right"></i></a>'
            . '<a class="btn btn-sm btn-light" href="?new=1&parent=' . (int) $c['id'] . '" title="Add subcategory"><i class="bi bi-plus-lg"></i></a>'
            . '<a class="btn btn-sm btn-outline-secondary" href="?edit=' . (int) $c['id'] . '">Edit</a>'
            . '<form method="post" class="d-inline">' . csrf_field() . '<input type="hidden" name="act" value="toggle"><input type="hidden" name="id" value="' . (int) $c['id'] . '"><button class="btn btn-sm ' . ($c['is_active'] ? 'btn-outline-warning' : 'btn-outline-success') . '">' . ($c['is_active'] ? 'Disable' : 'Enable') . '</button></form>'
            . '<form method="post" class="d-inline" data-confirm="Delete this category? Products will be kept.">' . csrf_field() . '<input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="' . (int) $c['id'] . '"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>'
            . '</div>' . cat_tree_html((int) $c['id'], $counts) . '</li>';
    }
    return $h . '</ul>';
}

admin_header('Categories', 'categories');
if ($showForm):
    $c = $edit ?: ['id' => 0, 'parent_id' => input_int('parent') ?: null, 'name' => '', 'name_urdu' => '', 'slug' => '', 'description' => '', 'icon' => '', 'image' => null, 'image_alt' => '', 'sort_order' => 0, 'is_active' => 1, 'show_in_menu' => 1, 'show_on_home' => 0];
    $parents = ['' => '— None (main category) —'];
    $exclude = $c['id'] ? category_descendant_ids((int) $c['id'], false) : [];
    foreach (category_options() as $o) if (!in_array((int) $o['id'], $exclude, true)) $parents[$o['id']] = $o['label'];
    $seo = seo_get('category', (int) $c['id']);
?>
<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card mb-3"><div class="card-header"><?= $c['id'] ? 'Edit category' : 'New category' ?></div><div class="card-body">
        <div class="row">
          <div class="col-md-6"><?= af_text('name', 'Name *', $c['name'], ['required' => true, 'maxlength' => 150, 'class' => 'form-control js-slug-source', 'data-slug' => '#f_slug']) ?></div>
          <div class="col-md-6"><?= af_text('name_urdu', 'Urdu name (optional)', $c['name_urdu'], ['dir' => 'rtl', 'maxlength' => 150]) ?></div>
          <div class="col-md-6"><?= af_select('parent_id', 'Parent category', $parents, $c['parent_id']) ?></div>
          <div class="col-md-6"><?= af_text('slug', 'URL slug', $c['slug'], ['class' => 'form-control js-slug', 'maxlength' => 160], 'Changing it creates a 301 redirect from the old URL automatically.') ?></div>
          <div class="col-12"><?= af_textarea('description', 'Description', $c['description'], ['rows' => 4]) ?></div>
          <div class="col-md-4"><?= af_text('icon', 'Menu icon (Bootstrap icon)', $c['icon'], ['placeholder' => 'bi-book'], '<a href="https://icons.getbootstrap.com" target="_blank" rel="noopener">Browse icons</a>') ?></div>
          <div class="col-md-4"><?= af_text('sort_order', 'Display order', $c['sort_order'], ['type' => 'number']) ?></div>
          <div class="col-md-4"><?= af_text('image_alt', 'Image ALT text', $c['image_alt'], ['maxlength' => 190]) ?></div>
          <div class="col-12"><?= af_image('image', 'Category image', $c['image']) ?></div>
        </div>
      </div></div>
      <div class="card"><div class="card-header">SEO</div><div class="card-body"><?= af_seo_fields($seo, 'category/', $c['id'] ? ltrim(substr(category_url((int) $c['id']), strlen(base_path()) + 10), '/') : '') ?></div></div>
    </div>
    <div class="col-lg-4">
      <div class="card"><div class="card-body">
        <?= af_check('is_active', 'Active (visible on website)', $c['is_active']) ?>
        <?= af_check('show_in_menu', 'Show in navigation menu', $c['show_in_menu']) ?>
        <?= af_check('show_on_home', 'Show in homepage "Shop by Categories"', $c['show_on_home']) ?>
        <hr><button class="btn btn-brand w-100">Save category</button>
        <a href="<?= aurl('categories.php') ?>" class="btn btn-link w-100">Cancel</a>
      </div></div>
    </div>
  </div>
</form>
<?php else: ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <p class="text-muted mb-0 small">Main Category → Subcategory → Child category. Drag <i class="bi bi-grip-vertical"></i> to reorder within a level.</p>
  <a href="?new=1" class="btn btn-brand"><i class="bi bi-plus-lg me-1"></i>Add category</a>
</div>
<div class="a-tree"><?= cat_tree_html(null, $counts) ?: '<div class="card card-body text-muted">No categories yet.</div>' ?></div>
<?php endif;
admin_footer(); ?>
<script>
// Lightweight drag & drop ordering (HTML5) within the same parent list
document.querySelectorAll('.js-sortable').forEach(function (ul) {
  var dragged = null;
  ul.querySelectorAll(':scope > li').forEach(function (li) {
    var handle = li.querySelector(':scope > .row-item .drag-handle');
    handle.addEventListener('mousedown', function () { li.draggable = true; });
    li.addEventListener('dragstart', function (e) { if (e.target !== li) return; dragged = li; li.classList.add('sortable-ghost'); e.stopPropagation(); });
    li.addEventListener('dragend', function () { li.classList.remove('sortable-ghost'); li.draggable = false;
      var ids = Array.prototype.map.call(ul.querySelectorAll(':scope > li'), function (x) { return x.dataset.id; });
      FSA.post('categories.php', { act: 'reorder', order: ids }).done(function () { FSA.toast('Order saved'); });
    });
    li.addEventListener('dragover', function (e) { if (!dragged || dragged.parentNode !== ul || dragged === li) return; e.preventDefault(); e.stopPropagation();
      var r = li.getBoundingClientRect(); ul.insertBefore(dragged, (e.clientY - r.top) > r.height / 2 ? li.nextSibling : li); });
  });
});
</script>
