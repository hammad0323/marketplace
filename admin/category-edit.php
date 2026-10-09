<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('categories.manage');
$id = input_int('id', 0, 'get');
$cat = $id ? db_one('SELECT * FROM categories WHERE id = ?', [$id]) : null;
$errors = [];
if (is_post()) {
    require_csrf();
    $d = [
        'name' => input('name'),
        'slug' => slugify(input('slug') ?: input('name')),
        'parent_id' => input_int('parent_id') ?: null,
        'short_description' => mb_substr(input('short_description'), 0, 255) ?: null,
        'description' => sanitize_html($_POST['description'] ?? '') ?: null,
        'image_alt' => mb_substr(input('image_alt'), 0, 190) ?: null,
        'seo_title' => mb_substr(input('seo_title'), 0, 190) ?: null,
        'meta_description' => mb_substr(input('meta_description'), 0, 320) ?: null,
        'sort_order' => input_int('sort_order'),
        'is_active' => input_bool('is_active'),
        'show_on_home' => input_bool('show_on_home'),
    ];
    if ($d['name'] === '') {
        $errors[] = 'Name is required.';
    }
    if (db_val('SELECT COUNT(*) FROM categories WHERE slug = ? AND id <> ?', [$d['slug'], $id])) {
        $errors[] = 'Slug already in use.';
    }
    if ($d['parent_id']) {
        // Prevent cycles: the parent may not be this category or one of its descendants.
        $walk = $d['parent_id'];
        $guard = 0;
        while ($walk && $guard++ < 20) {
            if ($walk === $id) {
                $errors[] = 'A category cannot be placed inside itself or its own subcategory.';
                break;
            }
            $walk = (int) db_val('SELECT parent_id FROM categories WHERE id = ?', [$walk]);
        }
    }
    [$img, $e1] = handle_image_field('image', $cat['image'] ?? null, 'categories');
    [$banner, $e2] = handle_image_field('banner_image', $cat['banner_image'] ?? null, 'categories');
    foreach (array_filter([$e1, $e2]) as $er) {
        $errors[] = $er;
    }
    $d['image'] = $img ?: null;
    $d['banner_image'] = $banner ?: null;
    if (!$errors) {
        if ($cat) {
            db_update('categories', $d, 'id = ?', [$id]);
            if ($cat['slug'] !== $d['slug']) {
                add_slug_redirect('category', $cat['slug'], $d['slug']);
            }
            if ($d['parent_id'] && !$cat['parent_id']) {
                // Became a subcategory: keep product assignments consistent.
                db_exec('UPDATE products SET category_id = ?, subcategory_id = ? WHERE category_id = ? AND subcategory_id IS NULL', [$d['parent_id'], $id, $id]);
            }
        } else {
            $id = db_insert('categories', $d);
        }
        audit_log($cat ? 'category_updated' : 'category_created', 'category', $id, ['name' => $d['name']]);
        flash('success', 'Category saved.');
        redirect(admin_url('category-edit', ['id' => $id]));
    }
    $cat = array_merge($cat ?? [], $d);
}
$c = $cat ?? ['is_active' => 1, 'sort_order' => 0, 'show_on_home' => 0];
admin_header($id ? 'Edit category' : 'Add category', 'categories');
?>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
<div class="row g-3">
  <div class="col-lg-8"><div class="card"><div class="card-body">
    <?= f_text('name', 'Name', $c['name'] ?? '', ['required' => true, 'data-slug-source' => '#f_slug']) ?>
    <?= f_text('slug', 'URL slug', $c['slug'] ?? '', ['data-slug-target' => true], '/category/slug — changing it adds a 301 redirect.') ?>
    <?= f_select('parent_id', 'Parent category', category_options(true, $id ?: null), $c['parent_id'] ?? '', [], 'Leave empty for a top-level category.') ?>
    <?= f_text('short_description', 'Short description (cards)', $c['short_description'] ?? '', ['maxlength' => 255]) ?>
    <?= f_textarea('description', 'Description (category page)', $c['description'] ?? '', ['rows' => 6]) ?>
    <h2 class="h6 mt-4">SEO</h2>
    <?= f_text('seo_title', 'SEO title', $c['seo_title'] ?? '', ['data-count' => 60, 'maxlength' => 190]) ?>
    <?= f_textarea('meta_description', 'Meta description', $c['meta_description'] ?? '', ['rows' => 2, 'data-count' => 160, 'maxlength' => 320]) ?>
  </div></div></div>
  <div class="col-lg-4"><div class="card"><div class="card-body">
    <?= f_check('is_active', 'Active', (int) $c['is_active']) ?>
    <?= f_check('show_on_home', 'Show in homepage "Shop by category"', (int) $c['show_on_home']) ?>
    <?= f_number('sort_order', 'Display order', $c['sort_order'], ['step' => 1]) ?>
    <?= f_image('image', 'Card image (portrait 9:11)', $c['image'] ?? null) ?>
    <?= f_text('image_alt', 'Image alt text', $c['image_alt'] ?? '') ?>
    <?= f_image('banner_image', 'Page banner (wide)', $c['banner_image'] ?? null) ?>
  </div></div></div>
</div>
<div class="sticky-actions"><?= f_submit() ?> <a href="<?= e(admin_url('categories')) ?>" class="btn btn-light">Back</a></div>
</form>
<?php admin_footer();
