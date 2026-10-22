<?php
require __DIR__ . '/../includes/init.php';
admin_require('categories');
$id = input_int('id');
$cat = $id ? db_row('SELECT * FROM categories WHERE id = ?', [$id]) : null;
$errors = [];
if (is_post()) {
    $d = [
        'name' => mb_substr(input('name', '', 'post'), 0, 120),
        'parent_id' => input_int('parent_id') ?: null,
        'description' => nullable(mb_substr(input('description', '', 'post'), 0, 2000)),
        'seo_title' => nullable(mb_substr(input('seo_title', '', 'post'), 0, 190)),
        'meta_description' => nullable(mb_substr(input('meta_description', '', 'post'), 0, 320)),
        'sort_order' => input_int('sort_order'),
        'is_active' => input_bool('is_active'),
        'show_on_home' => input_bool('show_on_home'),
        'show_in_menu' => input_bool('show_in_menu'),
        'noindex' => input_bool('noindex'),
    ];
    $d['slug'] = unique_slug('categories', input('slug', '', 'post') ?: $d['name'], $id);
    if (mb_strlen($d['name']) < 2) $errors[] = 'Name is required.';
    if ($id && $d['parent_id'] && in_array($d['parent_id'], category_descendant_ids($id, false), true)) $errors[] = 'A category cannot be placed inside itself or its own subcategory.';
    if (!$errors) {
        $d['image'] = a_try(fn() => a_image_value('image', $cat['image'] ?? null), $cat['image'] ?? null);
        $d['banner_image'] = a_try(fn() => a_image_value('banner_image', $cat['banner_image'] ?? null), $cat['banner_image'] ?? null);
        $d['og_image'] = a_try(fn() => a_image_value('og_image', $cat['og_image'] ?? null), $cat['og_image'] ?? null);
        if ($cat) {
            if ($cat['slug'] !== $d['slug']) {
                redirect_add('/category/' . $cat['slug'], '/category/' . $d['slug']);
            }
            db_update('categories', $d, 'id = ?', [$id]);
        } else {
            $id = db_insert('categories', $d);
        }
        audit_log($cat ? 'category_updated' : 'category_created', 'category', $id, ['name' => $d['name']]);
        flash('success', 'Category saved.');
        redirect('admin/categories/edit.php?id=' . $id);
    }
    $cat = array_merge($cat ?? [], $d);
}
$c = $cat ?? ['is_active' => 1, 'show_in_menu' => 1, 'sort_order' => 0];
$parents = ['' => '— Top level —'];
foreach (category_options(false) as $o) {
    if ($id && in_array($o['id'], category_descendant_ids($id, false), true)) continue;
    $parents[$o['id']] = $o['label'];
}
admin_header($id ? 'Edit category' : 'Add category');
?>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
<div class="row g-4"><div class="col-lg-8">
<div class="card a-card"><div class="card-body">
    <?= a_input('name', 'Name *', $c['name'] ?? '', 'text', ['required' => true, 'data-slug-source' => $id ? false : '#f_slug']) ?>
    <?= a_input('slug', 'URL slug', $c['slug'] ?? '', 'text', ['data-slug-target' => true], 'Store URL: /category/<b>slug</b>. Old URLs redirect automatically when changed.') ?>
    <?= a_select('parent_id', 'Parent category', $parents, $c['parent_id'] ?? '') ?>
    <?= a_textarea('description', 'Description', $c['description'] ?? '', 3) ?>
    <?= a_image('image', 'Category image (cards, menus — portrait works best)', $c['image'] ?? null) ?>
    <?= a_image('banner_image', 'Banner image (category page header — wide, 1920px)', $c['banner_image'] ?? null) ?>
</div></div>
<div class="card a-card"><div class="card-header">SEO</div><div class="card-body">
    <?= a_input('seo_title', 'SEO title', $c['seo_title'] ?? '') ?>
    <?= a_textarea('meta_description', 'Meta description', $c['meta_description'] ?? '', 2) ?>
    <?= a_image('og_image', 'Social share image', $c['og_image'] ?? null) ?>
    <?= a_check('noindex', 'Hide from search engines (noindex)', (int) ($c['noindex'] ?? 0)) ?>
</div></div>
</div><div class="col-lg-4">
<div class="card a-card"><div class="card-body">
    <?= a_check('is_active', 'Active', (int) $c['is_active']) ?>
    <?= a_check('show_on_home', 'Show on homepage “Shop by Category”', (int) ($c['show_on_home'] ?? 0)) ?>
    <?= a_check('show_in_menu', 'Show in navigation menus', (int) $c['show_in_menu']) ?>
    <?= a_input('sort_order', 'Display order', $c['sort_order'], 'number') ?>
    <button class="btn btn-dark w-100">Save category</button>
    <?php if ($id): ?><a class="btn btn-link w-100 mt-2" href="<?= e(url('category/' . $c['slug'])) ?>" target="_blank">View on store</a><?php endif; ?>
</div></div>
</div></div>
</form>
<?php admin_footer();
