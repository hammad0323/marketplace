<?php
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin('categories.manage');
$errors = [];
$editId = (int)get('edit');

if (is_post()) {
    csrf_check();
    $act = post('action');
    if ($act === 'reorder') {
        foreach (array_values((array)($_POST['ids'] ?? [])) as $i => $cid) db_exec('UPDATE categories SET sort_order = ? WHERE id = ?', [$i, (int)$cid]);
        audit('category_reorder', 'category');
        json_out(['ok' => true]);
    }
    if ($act === 'delete') {
        $cid = (int)post('id');
        $c = db_one('SELECT * FROM categories WHERE id = ?', [$cid]);
        if ($c) {
            db_exec('UPDATE categories SET parent_id = ? WHERE parent_id = ?', [$c['parent_id'], $cid]); // lift children up a level
            db_exec('DELETE FROM categories WHERE id = ?', [$cid]);
            audit('category_delete', 'category', $cid, ['name' => $c['name']]);
            flash('success', 'Category deleted. Its subcategories moved up one level; products keep their other categories.');
        }
        redirect(admin_url('categories'));
    }
    if ($act === 'save') {
        $cid = (int)post('id');
        $old = $cid ? db_one('SELECT * FROM categories WHERE id = ?', [$cid]) : null;
        $d = [
            'name' => mb_substr(post('name'), 0, 150),
            'slug' => slugify(post('slug') ?: post('name')),
            'parent_id' => (int)post('parent_id') ?: null,
            'description' => mb_substr(post('description'), 0, 3000),
            'seo_title' => mb_substr(post('seo_title'), 0, 190) ?: null,
            'meta_description' => mb_substr(post('meta_description'), 0, 320) ?: null,
            'show_on_home' => post('show_on_home') ? 1 : 0,
            'noindex' => post('noindex') ? 1 : 0,
            'status' => post('status') === 'inactive' ? 'inactive' : 'active',
            'sort_order' => (int)post('sort_order'),
        ];
        if (!v_len($d['name'], 2, 150)) $errors[] = 'Name is required.';
        if (db_val('SELECT id FROM categories WHERE slug = ? AND id <> ?', [$d['slug'], $cid])) $errors[] = 'This slug is already in use.';
        if ($cid && $d['parent_id'] && in_array($d['parent_id'], category_descendant_ids($cid, false), true)) $errors[] = 'A category cannot be placed inside itself or its own subcategory.';
        if (!$errors) {
            try {
                $d['image'] = f_image_value('image', $old['image'] ?? null, 'categories');
                $d['banner_image'] = f_image_value('banner_image', $old['banner_image'] ?? null, 'categories');
                $d['og_image'] = f_image_value('og_image', $old['og_image'] ?? null, 'categories');
                if ($cid) {
                    db_exec('UPDATE categories SET ' . implode(', ', array_map(fn($k) => "$k = ?", array_keys($d))) . ' WHERE id = ?', array_merge(array_values($d), [$cid]));
                    if ($old['slug'] !== $d['slug']) redirect_add_auto('/category/' . $old['slug'], '/category/' . $d['slug']);
                } else {
                    $cid = db_insert('INSERT INTO categories (' . implode(',', array_keys($d)) . ') VALUES (' . db_in($d) . ')', array_values($d));
                }
                audit($old ? 'category_update' : 'category_create', 'category', $cid, ['name' => $d['name']]);
                flash('success', 'Category saved.');
                redirect(admin_url('categories'));
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
        $editId = $cid ?: -1;
    }
}

$counts = [];
foreach (db_all('SELECT c.id, (SELECT COUNT(DISTINCT p.id) FROM products p LEFT JOIN product_categories pc ON pc.product_id = p.id WHERE p.category_id = c.id OR p.subcategory_id = c.id OR pc.category_id = c.id) n FROM categories c') as $r) $counts[(int)$r['id']] = (int)$r['n'];
$tree = categories_tree(false);
$edit = $editId > 0 ? db_one('SELECT * FROM categories WHERE id = ?', [$editId]) : ($editId === -1 || get('new') ? ($_POST ?: []) : null);

$admin_title = 'Categories';
require __DIR__ . '/partials/header.php';
$renderTree = function (array $nodes, int $depth) use (&$renderTree, $counts) {
    echo '<div' . ($depth === 0 ? ' data-sortable="' . e(admin_url('categories')) . '"' : ' data-sortable="' . e(admin_url('categories')) . '" class="ms-4"') . '>';
    foreach ($nodes as $n) {
        echo '<div data-id="' . (int)$n['id'] . '"><div class="section-row"><i class="bi bi-grip-vertical drag-handle"></i>';
        echo '<img src="' . e(img_url($n['image'])) . '" class="thumb" alt="">';
        echo '<div class="flex-grow-1"><strong>' . e($n['name']) . '</strong> ' . status_badge($n['status']) . ($n['show_on_home'] ? ' <span class="badge text-bg-light">Homepage</span>' : '') . '<div class="small text-muted">/category/' . e($n['slug']) . ' · ' . ($counts[(int)$n['id']] ?? 0) . ' products</div></div>';
        echo '<a class="btn btn-sm btn-light" href="' . e(url('category/' . $n['slug'])) . '" target="_blank"><i class="bi bi-eye"></i></a> <a class="btn btn-sm btn-light" href="?edit=' . (int)$n['id'] . '"><i class="bi bi-pencil"></i></a>';
        echo '<form method="post" data-confirm="Delete “' . e($n['name']) . '”?">' . csrf_field() . '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int)$n['id'] . '"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form></div>';
        if ($n['children']) $renderTree($n['children'], $depth + 1);
        echo '</div>';
    }
    echo '</div>';
};
?>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<div class="row g-3">
  <div class="col-xl-7">
    <div class="d-flex mb-2"><span class="small text-muted">Drag to reorder within a level. Unlimited nesting is supported.</span><a href="?new=1" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-plus-lg"></i> Add category</a></div>
    <?php $renderTree($tree, 0); ?>
  </div>
  <div class="col-xl-5">
    <?php if ($edit !== null): $ev = fn($k, $d = '') => $edit[$k] ?? $d; ?>
    <div class="card"><div class="card-header"><?= !empty($edit['id']) ? 'Edit category' : 'New category' ?></div><div class="card-body">
      <form method="post" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$ev('id', 0) ?>">
        <?= f_text('name', 'Name', $ev('name'), ['required' => true]) ?>
        <?= f_text('slug', 'URL slug', $ev('slug'), ['help' => '/category/slug — changing it creates a redirect from the old URL.']) ?>
        <?= f_select('parent_id', 'Parent category', categories_options(false, (int)$ev('id', 0) ?: null), $ev('parent_id'), ['empty' => '— Top level —']) ?>
        <?= f_text('description', 'Description', $ev('description'), ['type' => 'textarea', 'rows' => 3]) ?>
        <?= f_image('image', 'Category image (cards)', $ev('image') ?: null) ?>
        <?= f_image('banner_image', 'Banner image (category page header, optional)', $ev('banner_image') ?: null) ?>
        <div class="row"><div class="col-6"><?= f_select('status', 'Status', ['active' => 'Active', 'inactive' => 'Inactive'], $ev('status', 'active')) ?></div><div class="col-6"><?= f_text('sort_order', 'Sort order', $ev('sort_order', 0), ['type' => 'number']) ?></div></div>
        <?= f_toggle('show_on_home', 'Show on homepage', $ev('show_on_home')) ?>
        <hr><h6>SEO</h6>
        <?= f_text('seo_title', 'SEO title', $ev('seo_title')) ?>
        <?= f_text('meta_description', 'Meta description', $ev('meta_description'), ['type' => 'textarea', 'rows' => 2]) ?>
        <?= f_image('og_image', 'Social sharing image (optional)', $ev('og_image') ?: null) ?>
        <?= f_toggle('noindex', 'Hide from search engines', $ev('noindex')) ?>
        <button class="btn btn-primary">Save category</button> <a href="<?= e(admin_url('categories')) ?>" class="btn btn-light">Cancel</a>
      </form>
    </div></div>
    <?php else: ?><div class="card"><div class="card-body text-muted">Select a category to edit, or add a new one.</div></div><?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
