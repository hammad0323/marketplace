<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('collections.manage');
$id = input_int('id', 0, 'get');
$col = $id ? db_one('SELECT * FROM collections WHERE id = ?', [$id]) : null;
$errors = [];
if (is_post()) {
    require_csrf();
    $d = [
        'name' => input('name'), 'slug' => slugify(input('slug') ?: input('name')),
        'description' => mb_substr(input('description'), 0, 5000) ?: null, 'image_alt' => mb_substr(input('image_alt'), 0, 190) ?: null,
        'seo_title' => mb_substr(input('seo_title'), 0, 190) ?: null, 'meta_description' => mb_substr(input('meta_description'), 0, 320) ?: null,
        'sort_order' => input_int('sort_order'), 'is_active' => input_bool('is_active'),
    ];
    if ($d['name'] === '') {
        $errors[] = 'Name is required.';
    }
    if (db_val('SELECT COUNT(*) FROM collections WHERE slug = ? AND id <> ?', [$d['slug'], $id])) {
        $errors[] = 'Slug already in use.';
    }
    [$img, $err] = handle_image_field('image', $col['image'] ?? null, 'collections');
    if ($err) {
        $errors[] = $err;
    }
    $d['image'] = $img ?: null;
    if (!$errors) {
        db_tx(function () use (&$id, $col, $d) {
            if ($col) {
                db_update('collections', $d, 'id = ?', [$id]);
                if ($col['slug'] !== $d['slug']) {
                    add_slug_redirect('collection', $col['slug'], $d['slug']);
                }
            } else {
                $id = db_insert('collections', $d);
            }
            db_exec('DELETE FROM collection_products WHERE collection_id = ?', [$id]);
            foreach (array_values(array_unique(array_map('intval', input_array('products')))) as $k => $pid) {
                db_exec('INSERT IGNORE INTO collection_products (collection_id, product_id, sort_order) SELECT ?, id, ? FROM products WHERE id = ?', [$id, $k, $pid]);
            }
        });
        audit_log($col ? 'collection_updated' : 'collection_created', 'collection', $id, ['name' => $d['name']]);
        flash('success', 'Collection saved.');
        redirect(admin_url('collection-edit', ['id' => $id]));
    }
    $col = array_merge($col ?? [], $d);
}
$c = $col ?? ['is_active' => 1, 'sort_order' => 0];
$selected = $id ? db_col('SELECT product_id FROM collection_products WHERE collection_id = ? ORDER BY sort_order', [$id]) : [];
admin_header($id ? 'Edit collection' : 'Add collection', 'collections');
?>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
<div class="row g-3">
  <div class="col-lg-8"><div class="card"><div class="card-body">
    <?= f_text('name', 'Name', $c['name'] ?? '', ['required' => true, 'data-slug-source' => '#f_slug']) ?>
    <?= f_text('slug', 'URL slug', $c['slug'] ?? '', ['data-slug-target' => true], '/collection/slug') ?>
    <?= f_textarea('description', 'Description', $c['description'] ?? '', ['rows' => 4]) ?>
    <label class="form-label">Products in this collection</label>
    <input class="form-control form-control-sm mb-2" placeholder="Filter products…" data-filter-list="#colProducts">
    <div id="colProducts" class="border rounded p-2 bg-white" style="max-height:340px;overflow:auto">
      <?php foreach (product_options() as $pid => $label): ?>
        <label class="form-check d-block" data-filter-item><input class="form-check-input" type="checkbox" name="products[]" value="<?= (int) $pid ?>" <?= in_array($pid, $selected) ? 'checked' : '' ?>> <?= e($label) ?></label>
      <?php endforeach; ?>
    </div>
    <h2 class="h6 mt-4">SEO</h2>
    <?= f_text('seo_title', 'SEO title', $c['seo_title'] ?? '', ['data-count' => 60]) ?>
    <?= f_textarea('meta_description', 'Meta description', $c['meta_description'] ?? '', ['rows' => 2, 'data-count' => 160]) ?>
  </div></div></div>
  <div class="col-lg-4"><div class="card"><div class="card-body">
    <?= f_check('is_active', 'Active', (int) $c['is_active']) ?>
    <?= f_number('sort_order', 'Display order', $c['sort_order'], ['step' => 1]) ?>
    <?= f_image('image', 'Collection image', $c['image'] ?? null) ?>
    <?= f_text('image_alt', 'Image alt text', $c['image_alt'] ?? '') ?>
  </div></div></div>
</div>
<div class="sticky-actions"><?= f_submit() ?> <a href="<?= e(admin_url('collections')) ?>" class="btn btn-light">Back</a></div>
</form>
<?php admin_footer();
