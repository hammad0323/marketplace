<?php
require __DIR__ . '/includes/init.php';
admin_require('products');
if (is_post()) {
    csrf_check();
    $id = input_int('id');
    if (input('act') === 'delete') {
        db_exec('UPDATE products SET brand_id = NULL WHERE brand_id = ?', [$id]);
        db_exec('UPDATE brands SET deleted_at = NOW(), is_active = 0, slug = CONCAT(slug, "-deleted-", id), name = CONCAT(name, " (deleted ", id, ")") WHERE id = ?', [$id]);
        flash('success', 'Brand deleted.');
        redirect('admin/brands.php');
    }
    $old = $id ? db_row('SELECT * FROM brands WHERE id = ?', [$id]) : null;
    $name = input('name');
    if ($name === '') { flash('danger', 'Name is required.'); redirect('admin/brands.php'); }
    if (db_val('SELECT id FROM brands WHERE name = ? AND id <> ?', [$name, $id])) { flash('danger', 'A brand with this name already exists.'); redirect('admin/brands.php' . ($id ? '?edit=' . $id : '')); }
    $data = ['name' => mb_substr($name, 0, 120), 'slug' => unique_slug('brands', input('slug') ?: $name, $id), 'description' => input('description') ?: null,
        'is_active' => input_int('is_active'), 'is_featured' => input_int('is_featured'), 'sort_order' => input_int('sort_order')];
    $data['logo'] = admin_save_image('logo', 'brands', $old['logo'] ?? null, 400);
    if ($id) db_update_row('brands', $data, 'id = ?', [$id]); else $id = db_insert_row('brands', $data);
    if ($old && $old['slug'] !== $data['slug']) seo_add_redirect('/brand/' . $old['slug'] . '/', '/brand/' . $data['slug'] . '/');
    seo_save('brand', $id, input_arr('seo'));
    if ($old && $old['name'] !== $data['name']) foreach (db_col('SELECT id FROM products WHERE brand_id = ?', [$id]) as $pid) product_rebuild_search((int) $pid);
    catalog_changed();
    flash('success', 'Brand saved.');
    redirect('admin/brands.php');
}
$edit = input_int('edit') ? db_row('SELECT * FROM brands WHERE id = ? AND deleted_at IS NULL', [input_int('edit')]) : null;
$q = input('q');
$rows = db_all("SELECT b.*, (SELECT COUNT(*) FROM products p WHERE p.brand_id = b.id AND p.deleted_at IS NULL) AS cnt FROM brands b WHERE b.deleted_at IS NULL"
    . ($q !== '' ? ' AND b.name LIKE ?' : '') . ' ORDER BY b.sort_order, b.name', $q !== '' ? ['%' . db_like($q) . '%'] : []);
admin_header('Brands', 'brands');
$b = $edit ?: ['id' => 0, 'name' => '', 'slug' => '', 'description' => '', 'logo' => null, 'is_active' => 1, 'is_featured' => 0, 'sort_order' => 0];
?>
<div class="row g-3">
  <div class="col-lg-7">
    <form class="a-filter"><input name="q" class="form-control form-control-sm" placeholder="Search brands" value="<?= e($q) ?>"><button class="btn btn-sm btn-light">Search</button></form>
    <div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
      <thead><tr><th></th><th>Brand</th><th>Products</th><th>Status</th><th></th></tr></thead><tbody>
      <?php foreach ($rows as $r): ?>
      <tr><td><img src="<?= e(upload_url($r['logo'])) ?>" class="a-thumb" alt=""></td><td><strong><?= e($r['name']) ?></strong><br><small class="text-muted">/brand/<?= e($r['slug']) ?>/</small></td>
        <td><?= (int) $r['cnt'] ?></td><td><?= $r['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Hidden</span>' ?> <?= $r['is_featured'] ? '<span class="badge bg-warning text-dark">Featured</span>' : '' ?></td>
        <td class="text-end text-nowrap"><a href="?edit=<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
          <form method="post" class="d-inline" data-confirm="Delete this brand? Products keep existing without a brand."><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
      <?php endforeach; ?>
      </tbody></table></div></div>
  </div>
  <div class="col-lg-5">
    <form method="post" enctype="multipart/form-data" class="card">
      <div class="card-header"><?= $b['id'] ? 'Edit brand' : 'Add brand' ?></div>
      <div class="card-body">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
        <?= af_text('name', 'Name *', $b['name'], ['required' => true, 'class' => 'form-control js-slug-source', 'data-slug' => '#f_slug']) ?>
        <?= af_text('slug', 'URL slug', $b['slug'], ['class' => 'form-control js-slug']) ?>
        <?= af_textarea('description', 'Description', $b['description']) ?>
        <?= af_image('logo', 'Logo', $b['logo']) ?>
        <?= af_text('sort_order', 'Order', $b['sort_order'], ['type' => 'number']) ?>
        <?= af_check('is_active', 'Active', $b['is_active']) ?>
        <?= af_check('is_featured', 'Featured on homepage', $b['is_featured']) ?>
        <details class="mt-2"><summary class="fw-semibold">SEO settings</summary><div class="mt-2"><?= af_seo_fields(seo_get('brand', (int) $b['id']), 'brand/', $b['slug']) ?></div></details>
      </div>
      <div class="card-footer bg-white"><button class="btn btn-brand">Save brand</button> <?php if ($b['id']): ?><a href="<?= aurl('brands.php') ?>" class="btn btn-link">Cancel</a><?php endif; ?></div>
    </form>
  </div>
</div>
<?php admin_footer();
