<?php
require __DIR__ . '/../includes/init.php';
admin_require('collections');
$id = input_int('id');
$col = $id ? db_row('SELECT * FROM collections WHERE id = ?', [$id]) : null;
$errors = [];
if (is_post()) {
    $d = [
        'name' => mb_substr(input('name', '', 'post'), 0, 120),
        'description' => nullable(mb_substr(input('description', '', 'post'), 0, 2000)),
        'seo_title' => nullable(mb_substr(input('seo_title', '', 'post'), 0, 190)),
        'meta_description' => nullable(mb_substr(input('meta_description', '', 'post'), 0, 320)),
        'sort_order' => input_int('sort_order'),
        'is_active' => input_bool('is_active'),
        'show_on_home' => input_bool('show_on_home'),
        'noindex' => input_bool('noindex'),
    ];
    $d['slug'] = unique_slug('collections', input('slug', '', 'post') ?: $d['name'], $id);
    if (mb_strlen($d['name']) < 2) $errors[] = 'Name is required.';
    if (!$errors) {
        $d['image'] = a_try(fn() => a_image_value('image', $col['image'] ?? null), $col['image'] ?? null);
        $d['banner_image'] = a_try(fn() => a_image_value('banner_image', $col['banner_image'] ?? null), $col['banner_image'] ?? null);
        if ($col) {
            if ($col['slug'] !== $d['slug']) redirect_add('/collections/' . $col['slug'], '/collections/' . $d['slug']);
            db_update('collections', $d, 'id = ?', [$id]);
        } else {
            $id = db_insert('collections', $d);
        }
        db_exec('DELETE FROM collection_products WHERE collection_id = ?', [$id]);
        foreach (a_ids('products') as $i => $pid) {
            db_exec('INSERT IGNORE INTO collection_products (collection_id, product_id, sort_order) VALUES (?, ?, ?)', [$id, $pid, $i]);
        }
        audit_log($col ? 'collection_updated' : 'collection_created', 'collection', $id, ['name' => $d['name']]);
        flash('success', 'Collection saved.');
        redirect('admin/collections/edit.php?id=' . $id);
    }
    $col = array_merge($col ?? [], $d);
}
$c = $col ?? ['is_active' => 1, 'sort_order' => 0];
$selected = $id ? db_col('SELECT product_id FROM collection_products WHERE collection_id = ? ORDER BY sort_order', [$id]) : [];
admin_header($id ? 'Edit collection' : 'Add collection');
?>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
<div class="row g-4"><div class="col-lg-8">
<div class="card a-card"><div class="card-body">
    <?= a_input('name', 'Title *', $c['name'] ?? '', 'text', ['required' => true, 'data-slug-source' => $id ? false : '#f_slug']) ?>
    <?= a_input('slug', 'URL slug', $c['slug'] ?? '', 'text', ['data-slug-target' => true], 'Store URL: /collections/<b>slug</b>') ?>
    <?= a_textarea('description', 'Description', $c['description'] ?? '', 3) ?>
    <?= a_image('image', 'Collection image (portrait, used on cards)', $c['image'] ?? null) ?>
    <?= a_image('banner_image', 'Banner image (collection page header)', $c['banner_image'] ?? null) ?>
    <?= a_picker('products', 'Products in this collection (in display order)', 'products', $selected) ?>
</div></div>
</div><div class="col-lg-4">
<div class="card a-card"><div class="card-body">
    <?= a_check('is_active', 'Active', (int) $c['is_active']) ?>
    <?= a_check('show_on_home', 'Show on homepage', (int) ($c['show_on_home'] ?? 0)) ?>
    <?= a_input('sort_order', 'Display order', $c['sort_order'], 'number') ?>
    <?= a_input('seo_title', 'SEO title', $c['seo_title'] ?? '') ?>
    <?= a_textarea('meta_description', 'Meta description', $c['meta_description'] ?? '', 2) ?>
    <?= a_check('noindex', 'noindex', (int) ($c['noindex'] ?? 0)) ?>
    <button class="btn btn-dark w-100">Save collection</button>
</div></div>
</div></div></form>
<?php admin_footer();
