<?php
require __DIR__ . '/includes/admin.php';

$id = (int)get('id');
$cat = $id ? row('SELECT * FROM categories WHERE id = ?', [$id]) : null;
$blank = ['parent_id' => get('parent', ''), 'name' => '', 'slug' => '', 'description' => '', 'image' => '', 'banner' => '', 'sort_order' => 0, 'status' => 1, 'show_home' => 1, 'show_menu' => 1, 'meta_title' => '', 'meta_description' => '', 'meta_keywords' => ''];
$d = $cat ?: $blank;

if (is_post()) {
    require_csrf();
    if (post('do') === 'delete') {
        $del = (int)post('id');
        $kids = array_column(rows('SELECT id FROM categories WHERE parent_id = ?', [$del]), 'id');
        $all = array_merge([$del], $kids);
        $in = implode(',', array_map('intval', $all));
        q("UPDATE products SET category_id = NULL WHERE category_id IN ($in)");
        q("UPDATE products SET subcategory_id = NULL WHERE subcategory_id IN ($in)");
        foreach (rows("SELECT image, banner FROM categories WHERE id IN ($in)") as $r) { delete_upload($r['image']); delete_upload($r['banner']); }
        q("DELETE FROM categories WHERE id IN ($in)");
        flash('success', 'Category deleted' . ($kids ? ' along with ' . count($kids) . ' sub-categories' : '') . '. Its products were kept (uncategorised).');
        redirect('admin/categories');
    }
    if (post('do') === 'sort') {
        foreach ((array)($_POST['sort'] ?? []) as $cid => $ord) q('UPDATE categories SET sort_order = ? WHERE id = ?', [(int)$ord, (int)$cid]);
        flash('success', 'Order saved.');
        redirect('admin/categories');
    }
    foreach ($blank as $k => $v) if (!in_array($k, ['image', 'banner'], true)) $d[$k] = is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : $v;
    foreach (['status', 'show_home', 'show_menu'] as $k) $d[$k] = post($k) === '1' ? 1 : 0;
    if ($d['name'] === '') {
        flash('error', 'Category name is required.');
    } else {
        $parent = $d['parent_id'] !== '' && (int)$d['parent_id'] !== $id ? (int)$d['parent_id'] : null;
        if ($parent && val('SELECT parent_id FROM categories WHERE id = ?', [$parent])) $parent = null; // only 2 levels
        if ($parent && $id && val('SELECT COUNT(*) FROM categories WHERE parent_id = ?', [$id])) {
            $parent = null;
            flash('info', 'A category with sub-categories cannot itself become a sub-category.');
        }
        $slug = unique_slug('categories', $d['slug'] !== '' ? $d['slug'] : $d['name'], $id);
        $image = handle_image('image', $cat['image'] ?? null, 'categories');
        $banner = handle_image('banner', $cat['banner'] ?? null, 'categories');
        $vals = [$parent, $d['name'], $slug, $d['description'], $image, $banner, (int)$d['sort_order'], $d['status'], $d['show_home'], $d['show_menu'], $d['meta_title'], $d['meta_description'], $d['meta_keywords']];
        $set = 'parent_id=?, name=?, slug=?, description=?, image=?, banner=?, sort_order=?, status=?, show_home=?, show_menu=?, meta_title=?, meta_description=?, meta_keywords=?';
        if ($id) q("UPDATE categories SET $set WHERE id = ?", array_merge($vals, [$id]));
        else q("INSERT INTO categories SET $set", $vals);
        flash('success', 'Category saved.');
        redirect('admin/categories');
    }
}

$tree = rows('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id OR p.subcategory_id = c.id) n FROM categories c ORDER BY sort_order, name');
$parents = array_filter($tree, fn($c) => !$c['parent_id']);
admin_header('Categories', 'categories');
?>
<div class="grid-2 wide-left">
  <form method="post" class="card">
    <?= csrf_field() ?><input type="hidden" name="do" value="sort">
    <div class="card__head"><h3>All categories</h3><button class="btn btn-sm">Save order</button></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Name</th><th>Products</th><th>Home</th><th>Menu</th><th>Status</th><th>Order</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($parents as $c): ?>
        <?php foreach (array_merge([$c], array_filter($tree, fn($s) => (int)$s['parent_id'] === (int)$c['id'])) as $row): $isSub = (bool)$row['parent_id']; ?>
          <tr class="<?= $isSub ? 'sub-row' : 'parent-row' ?>">
            <td><div class="prod-cell"><img src="<?= e(img($row['image'])) ?>" alt=""><div><?= $isSub ? '<span class="muted">└</span> ' : '' ?><a href="?id=<?= $row['id'] ?>"><strong><?= e($row['name']) ?></strong></a><small class="block muted">/<?= e($row['slug']) ?></small></div></div></td>
            <td><?= (int)$row['n'] ?></td>
            <td><?= $row['show_home'] ? '✓' : '—' ?></td>
            <td><?= $row['show_menu'] ? '✓' : '—' ?></td>
            <td><?= $row['status'] ? '<span class="badge badge-delivered">Active</span>' : '<span class="badge">Hidden</span>' ?></td>
            <td><input class="w-num" type="number" name="sort[<?= $row['id'] ?>]" value="<?= (int)$row['sort_order'] ?>"></td>
            <td class="actions">
              <?php if (!$isSub): ?><a class="icon" href="?parent=<?= $row['id'] ?>" title="Add sub-category"><?= aicon('plus') ?></a><?php endif; ?>
              <a class="icon" href="?id=<?= $row['id'] ?>" title="Edit"><?= aicon('edit') ?></a>
              <a class="icon" href="<?= category_url($row) ?>" target="_blank" title="View"><?= aicon('eye') ?></a>
              <button class="icon danger" form="delCat" name="id" value="<?= $row['id'] ?>" data-confirm="Delete “<?= e($row['name']) ?>”<?= $isSub ? '' : ' and all its sub-categories' ?>? Products will be kept." title="Delete"><?= aicon('trash') ?></button>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </form>
  <form method="post" id="delCat"><?= csrf_field() ?><input type="hidden" name="do" value="delete"></form>

  <form method="post" enctype="multipart/form-data" class="card">
    <?= csrf_field() ?>
    <div class="card__head"><h3><?= $id ? 'Edit category' : 'Add category / sub-category' ?></h3><?php if ($id): ?><a class="link sm" href="<?= url('admin/categories') ?>">+ New</a><?php endif; ?></div>
    <?= f_text('name', 'Name *', $d['name'], ['attrs' => 'required data-slug-source']) ?>
    <?php $opts = ['' => '— Top level (main category) —']; foreach ($parents as $pc) if ((int)$pc['id'] !== $id) $opts[$pc['id']] = $pc['name']; ?>
    <?= f_select('parent_id', 'Parent', $d['parent_id'], $opts, ['help' => 'Pick a parent to make this a sub-category.']) ?>
    <?= f_text('slug', 'URL slug', $d['slug'], ['attrs' => 'data-slug-target']) ?>
    <?= f_text('description', 'Description', $d['description'], ['type' => 'textarea', 'rows' => 3]) ?>
    <?= f_image('image', 'Thumbnail image (homepage & menu)', $d['image'], 'Portrait 4:5, e.g. 800×1000') ?>
    <?= f_image('banner', 'Banner image (category page header)', $d['banner'], 'Wide, e.g. 1920×600') ?>
    <?= f_text('sort_order', 'Sort order', $d['sort_order'], ['type' => 'number']) ?>
    <?= f_switch('status', 'Active', $d['status']) ?>
    <?= f_switch('show_home', 'Show on homepage', $d['show_home']) ?>
    <?= f_switch('show_menu', 'Show in main menu', $d['show_menu']) ?>
    <details class="seo-box"><summary>SEO settings</summary>
      <?= f_text('meta_title', 'Meta title', $d['meta_title'], ['attrs' => 'data-count="60"']) ?>
      <?= f_text('meta_description', 'Meta description', $d['meta_description'], ['type' => 'textarea', 'rows' => 3, 'attrs' => 'data-count="160"']) ?>
      <?= f_text('meta_keywords', 'Meta keywords', $d['meta_keywords']) ?>
    </details>
    <button class="btn btn-primary btn-block" type="submit">Save Category</button>
  </form>
</div>
<?php admin_footer();
